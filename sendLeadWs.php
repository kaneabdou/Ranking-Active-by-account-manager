<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

class SendLead_sfam_es
{
    const USER = 'web';
    const PASS = 'web';
    const MAX_PROCESSES = 10;
    const CAPPING = 124505;
    /**
     * Database connexion
     * @var PDO
     */
    private $bdd;
    private $gender = ['Mr' => '1', 'Mrs' => '3', 'Miss' => '2'];
    //const BASE_URL = 'https://ws.b2s-group.com:8080/sfam/ws_sfam_source_spain_dev.php'; // DEV
    const BASE_URL = 'https://ws.b2s-group.com:8080/sfam/ws_sfam_source_spain'; // PROD

    /**
     * SendLead constructor.
     */
    public function __construct()
    {
        try {
            $this->connect();
            $this->bdd->exec("SET CHARACTER SET utf8");
        } catch (Exception $e) {
            die('Erreur connexion à la base : ' . $e->getMessage());
        }
    } // __construct

    /**
     * Connect to the database
     */
    private function connect()
    {
        $this->bdd = new PDO('mysql:host=192.168.43.19;port=3399;dbname=USER', SendLead::USER, SendLead::PASS);
        $this->bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } // connect

    /**
     * Select the leads to send
     *
     * @return boolean
     */
    public function selectLead()
    {
        // ==== Initializations ====
        $lsRequest = "SELECT * FROM USER.sfam_es WHERE sent is null "; // LIMIT 1";
        $loStmt = $this->bdd->prepare($lsRequest);
        $laData = [];
        echo date('Y-m-d H:i:s') . " - Sql Request: {$lsRequest}\n";

        // ==== Fetch all rows ====
        $loStmt->execute($laData);
        echo date('Y-m-d H:i:s') . " - Fetch all rows...\n";
        $laAllResults = $loStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($laAllResults)) {

            // ==== Read the rows ====
            echo date('Y-m-d H:i:s') . " - Read rows...\n";
            $liNbProcess = 0;
            $liCount = 0;
            foreach ($laAllResults as $laValues) {
                $liPid = pcntl_fork();
                if (-1 == $liPid) {
                    // ---- Error ----
                    echo "Impossible to fork\n";
                    exit;
                } elseif (0 == $liPid) {
                    // ---- Child ----
                    echo date('Y-m-d H:i:s') . " [{$liCount} / " . self::CAPPING . "] - Child sending {$laValues['email']}...\n";
                    $this->sendLead($laValues);
                    exit;
                }
                // ---- Father wait if process > 10 ----
                $liNbProcess++;
                if ($liNbProcess >= self::MAX_PROCESSES) {
                    echo date('Y-m-d H:i:s') . " - Father wait...\n";
                    pcntl_wait($liStatus);
                    $liNbProcess--;
                }
            } // foreach
            echo date('Y-m-d H:i:s') . " - End Of Sending...\n";
        } // if

        return false;
    } // selectLead

    /**
     * Send one lead by WS
     *
     * @param array $laValues Read values
     */
    private function sendLead($laValues)
    {
        $loDate = new \DateTime('now');
        $loDateConnect = \DateTime::createFromFormat('Y-m-d H:i:s', $laValues['dateconnect']);
        if ($laValues['phone'][0] != 0) {
            $lsPhone = '0' . $laValues['phone'];
        } else {
            $lsPhone = $laValues['phone'];
        }
        $lsGender = 1;
        if (isset($this->gender[$laValues['gender']])) {
            $lsGender = $this->gender[$laValues['gender']];
        }
        $laFields = [
            'nom_fichier'   => 'SFAM_JC_ES',
            'reference'     => $laValues['id'],
            'origine'       => 'co sponso',
            'civilite'      => $lsGender,
            'nom'           => $laValues['lastname'],
            'prenom'        => $laValues['firstname'],
            'code_postal'   => $laValues['zipcode'],
            'email'         => $laValues['email'],
            'type_collecte' => 'jeu concours',
            'date_collecte' => $loDateConnect->format('Y-m-d'),
            'pays'          => 'ES'
        ];
        $lsPhoneFirstDigits = str_split($lsPhone,2);
        if ($lsPhoneFirstDigits[0] == '06' || $lsPhoneFirstDigits[0] == '07') {
            $laFields['telephone_portable'] = $lsPhone;
        } else {
            $laFields['telephone_fixe'] = $lsPhone;
        }
        $lsData = json_encode($laFields);
        if (!$lsData) {
            var_dump($laFields);
            var_dump(json_last_error_msg());
        }
        $laPostFields = ['sfam_source' => $lsData];
        $lsTime = $loDate->getTimestamp(); // PROD
        $lsAccountName = 'sfam_cluster12_esp';
        $lsAccountKey = '5NxijjO5RyJHlkjhg4567hkghjg654ttE7uytre';
        $lsMethod = 'POST';
        $lsStringToSign = $lsMethod . "\n" . $lsAccountName . "\n" . $lsTime;
        $lsSignature = base64_encode(hash_hmac('sha256', $lsStringToSign, $lsAccountKey, true));
        $laHeader = [
            "Time: {$lsTime}",
            "Authorization: {$lsAccountName}:{$lsSignature}"
        ];
        $time_start = microtime(true);
        $loCurl = curl_init();
        curl_setopt($loCurl, CURLOPT_URL, self::BASE_URL);
        curl_setopt($loCurl, CURLOPT_POST, true);
        curl_setopt($loCurl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($loCurl,CURLOPT_HTTPHEADER, $laHeader);
        curl_setopt($loCurl,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($loCurl,CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($loCurl, CURLOPT_POSTFIELDS, $laPostFields);
        curl_setopt($loCurl, CURLOPT_RETURNTRANSFER, true);
        $lsResponse = curl_exec($loCurl);
        curl_close($loCurl);
        $lsCall = $lsData;
        //var_dump($lsCall); // DEBUG
        //var_dump($lsResponse); // DEBUG
        $time_end = microtime(true);
        $time = $time_end - $time_start;
        echo "Duree d'envoi : $time secondes \n";

        // ==== Insertion ====
        $this->insertLead($laValues['id'], $lsCall, $lsResponse);
    } // sendLead

    /**
     * insert the lead into adv
     *
     * @return id of the lead
     */
    private function insertLead($pidUser, $psCall, $psResponse)
    {
        // ==== Livraison ====
        if (preg_match('/New record created successfully/', $psResponse)) {
            $lsState = 'OK';
        } elseif(preg_match('/deja present/', $psResponse)) {
            $lsState = 'DUPLICATE';
        } else{
            $lsState = 'ERROR';
        }
        $this->connect();
        $stmt = $this->bdd->prepare("UPDATE USER.sfam_es SET ws_call=:call_ws, ws_return=:return_ws, sent=:status
                                     WHERE id=:user_id LIMIT 1");
        $stmt->execute([
            "user_id"   => $pidUser,
            "call_ws"   => $psCall,
            "return_ws" => $psResponse,
            "status"    => $lsState
        ]);
        echo "Reponse : $lsState \n";
    } // insertLead
}

// updateDateLogin

$Send = new SendLead();
$Send->selectLead();

// GRANT select, insert, update, delete ON PROD.egentic_bouygues to web@'%';