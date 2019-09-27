<?php

namespace ApiBundle\Model\Ranking;

use Doctrine\ODM\MongoDB\Mapping\Annotations\Integer;
use Doctrine\ORM\EntityManager;
use Quartz\ApiBundle\Entity\Common\AccessEntity;

/**
 * Get the ranking for monetization
 *
 * <pre>
 * Abdoul 03/12/2018 Création
 * </pre>
 * @author Abdoul
 * @version 1.0
 * @package ApiBundle
 */
class ActiveRanking
{
    /**
     * Doctrine entity manager
     *
     * @var EntityManager
     */
    private $manager;

    /**
     * @var array
     */
    public $perf;

    /**
     * @param EntityManager $poEntityManager
     */
    public function __construct(EntityManager $poEntityManager)
    {
       $this->manager = $poEntityManager;
    } // Construct

    /**
     * Get the account Manager icon
     *
     * @param integer $piEntityId
     * @return string
     */
    public function getIcon($piEntityId)
    {
        $lsIcon = '';
        if ($piEntityId == 3) {
            $lsIcon = 'taz';
        } elseif ($piEntityId == 15){
            $lsIcon = 'addict';
        } elseif ($piEntityId == 16) {
            $lsIcon = 'diagomail';
        } elseif ($piEntityId == 14) {
            $lsIcon = 'leadiance';
        } else {
            $lsIcon = 'natexo';
        }

        return $lsIcon;
    } // getIcon

    /**
     * Ranking for monetization
     *
     * @param array $paData
     * @return array
     */
    public function getActiveRankingByAccountManager(array $paData)
    {
        if (empty($paData['period'])) {
            $loDate = new \DateTime('now');
            $paData['period'] = $loDate->format('Ym');
        }
        $loDate = \DateTime::createFromFormat('Ymd', $paData['period'] . '01');
        $paData['previous_period'] = $loDate->modify('-1 month')->format('Ym');
        // ==== Get list of active by account manager ====
        $laListActive = $this->manager->getRepository('ApiBundle:Statistic\Monetizationstatisticsprograms')->getActiveAccountManager($paData);
        $laActiveByAccountManager = [];

        $laFilter = [
            $this->getFilters($paData),
        ];
            foreach ($laListActive as $laAccountManagerData) {
                if ($laAccountManagerData['actives_previous'] > 0 && $laAccountManagerData['actives_month'] > 0) {
                    $liPerformance = number_format((($laAccountManagerData['actives_month'] - $laAccountManagerData['actives_previous']) / $laAccountManagerData['actives_previous'])*100,'0','','');
                    if ($liPerformance != 0) {
                        /**
                         * @var AccessEntity $loEntity
                         */
                        $loEntity = $this->manager->getRepository('QuartzApiBundle:Common\AccessEntity')->find($laAccountManagerData['entityId']);
                        // ---- Return the list by Accounts Managers ----
                        $laActiveByAccountManager[] = [
                            'user'    => [
                                'name'     => $laAccountManagerData['firstName'] . ' ' . $laAccountManagerData['lastName'], // to display
                                'icon'     => $this->getIcon($laAccountManagerData['entityId']),
                                'entityName' =>$loEntity->getName(),
                                'id'       => $laAccountManagerData['id'],
                            ],
                            'country'      => $laAccountManagerData['country'],
                            'performance'  => $liPerformance
                        ];
                    }
                }

            } // endFor
            $this->triStat($laActiveByAccountManager);
            $laList=[
                'data'     => $this->perf,
                '_filters' => $laFilter
            ];

        return $laList;
    } // getActiveRankingByAccountManager

    /**
     * Sort the array by performance
     *
     * @param $paPerformance
     */
    public function triStat($paPerformance){
        uasort(
            $paPerformance,
            function ($pa, $pb) {
                return ($pa['performance'] > $pb['performance']) ? -1 : 1;
            }
        );
        foreach ($paPerformance as $laPerformance){
            $this->perf[] = $laPerformance;
        }
    } // Return the list of performance desc

    /**
     * Get all Filter
     *
     * @param array $paData
     * @return array
     */
    public function getFilters($paData)
    {
        // ==== Get entity  ====
        $laDatas = $this->manager->getRepository('QuartzApiBundle:Common\AccessEntity')->filtreEntity();
        $laEntities = [];
        /** @var AccessEntity $loEntity */
        foreach ($laDatas as $laData) {
            $laEntities['id:'.$laData['id']] = [
                'id' => $laData['id'],
                'name' => $laData['name']
            ];
        }
        // ==== Get all country ====
        $laData = $this->manager->getRepository('QuartzApiBundle:Common\AccessCountry')->findAll();
        $laCountries = [];
        /** @var AccessCountry $loCountry */
        foreach ($laData as $loCountry) {
            if ($loCountry->getVisible()) {
                $laCountries['id:'.$loCountry->getCode()] = [
                    'id' => $loCountry->getCode(),
                    'name' => $loCountry->getName()
                ];
            }
        }

        // ===== Program category type =======
        $laCategories = [
            ['id' => 'mainstream', 'name' => 'Mainstream'],
            ['id' => 'dating', 'name' => 'Dating'],
            ['id' => 'clairevoyance', 'name' => 'Clairevoyance'],
            ['id' => 'diet', 'name' => 'Diet']
        ];

        // ==== Get bu ====
        $laBusiness = $this->manager->getRepository('QuartzApiBundle:Common\AccessBu')->getBuForRanking($paData);

        // ==== Return the possible months filter ====
        /** @var \DateTime[][] $laDates */
        $laDates = $this->manager->getRepository('ApiBundle:Statistic\Monetizationstatisticsprograms')->getDistinctPeriod();
        $laDatesFilter = [];
        foreach ($laDates as $laData) {
            $lsYear = substr($laData['period'],0, 4);
            $lsMonth = substr($laData['period'], 4, 2);
            $laDatesFilter['id:' . $laData['period']] = ['id' => $laData['period'], 'name' => $lsMonth . '-' . $lsYear];
        }

        return [
            'bu'      => $laBusiness,
            'entity'  => $laEntities,
            'country' => $laCountries,
            'period'  => $laDatesFilter,
            'category'  =>$laCategories,
        ];
    } // getFilters


}