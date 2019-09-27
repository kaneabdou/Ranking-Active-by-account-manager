<?php
namespace ApiBundle\Entity\Statistic\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * Monetizationstatisticsprograms Repository
 *
 * <pre>
 * Abdoul 03/12/2018 Creation
 * </pre>
 * @author Abdoul
 * @version 1.0
 * @package APiBundle
 */
class MonetizationstatisticsprogramsRepository extends EntityRepository
{
    /**
     * Returns the active leads by Account manager
     *
     * @param array $paData Params of the function
     * @return array
     */
    public function getActiveAccountManager(array $paData)
    {
        $loQuery= $this->createQueryBuilder('p')
            ->select(
                "u.lastName, u.firstName, p.entityId, u.id,p.buId, u.country, p.programCategory, p.dateTime",
                "sum(if(p.dateTime = '{$paData['period']}', p.atSents / p.atDaysNumber, 0)) as actives_month",
	            "sum(if(p.dateTime = '{$paData['previous_period']}', p.atSents / p.atDaysNumber, 0)) as actives_previous")
            ->join('p.user','u')
            ->andWhere('p.dateTime = :period or p.dateTime = :previous')
            ->setParameter('period', $paData['period'])
            ->setParameter('previous', $paData['previous_period'])
            ->groupBy('u.id');

        // filter
        if (array_key_exists('bu', $paData) && !empty($paData['bu'])) {
            $laBu = explode(',', $paData['bu']);
            $loQuery->andWhere('p.buId in (:bu)')
                ->setParameter('bu', $laBu);
        }
        if (array_key_exists('entity' , $paData) && !empty($paData['entity'])) {
            $laEntity = explode(',',$paData['entity']);
           $loQuery->andWhere('p.entityId in (:entity)')
               ->setParameter('entity', $laEntity);
        }
        if (array_key_exists('country', $paData) && !empty($paData['country'])) {
            $laCountry = explode(',', $paData['country']);
            $loQuery->andWhere('p.country in (:country)')
                ->setParameter('country', $laCountry);
        }
        if( array_key_exists('category', $paData) && !empty($paData['category'])){
            $laCategory = explode(',', $paData['category']);
            $loQuery->andWhere('p.programCategory in (:category)')
                ->setParameter('category', $laCategory);
        }

        return $loQuery->getQuery()->getScalarResult();
    } // getActiveAccountManager

    /**
     * Returns the different periods
     *
     * @return array
     */
    public function getDistinctPeriod()
    {
        $loQuery= $this->createQueryBuilder('p')
            ->select('DISTINCT p.dateTime as period')
            ->orderBy('p.dateTime', 'DESC')
            ->getQuery();

        return $loQuery->getScalarResult();
    } // getDistinctPeriod

    /**
     * Returns the active leads by BU
     *
     * @param array $paData Params of the function
     * @return array
     */
    public function getActiveBusinessUnit(array $paData)
    {
        $loQuery= $this->createQueryBuilder('p')
            ->select(
                "p.buId",
                "sum(if(p.dateTime = '{$paData['period']}', p.atSents / p.atDaysNumber, 0)) as actives_month",
                "sum(if(p.dateTime = '{$paData['previous_period']}', p.atSents / p.atDaysNumber, 0)) as actives_previous")
            ->andWhere('p.dateTime = :period or p.dateTime = :previous')
            ->setParameter('period', $paData['period'])
            ->setParameter('previous', $paData['previous_period'])
            ->groupBy('p.buId');

        // filter
        if (array_key_exists('entity' , $paData) && !empty($paData['entity'])) {
            $laEntity = explode(',',$paData['entity']);
            $loQuery->andWhere('p.entityId in (:entity)')
                ->setParameter('entity', $laEntity);
        }
        if (array_key_exists('country', $paData) && !empty($paData['country'])) {
            $laCountry = explode(',', $paData['country']);
            $loQuery->andWhere('p.country in (:country)')
                ->setParameter('country', $laCountry);
        }
        if( array_key_exists('category', $paData) && !empty($paData['category'])){
            $laCategory = explode(',', $paData['category']);
            $loQuery->andWhere('p.programCategory in (:category)')
                ->setParameter('category', $laCategory);
        }

        return $loQuery->getQuery()->getResult();
    } // getActiveBu


    public function getMarginMonetByAccountManger($paData)
    {
        $loQuery = $this->createQueryBuilder('p')
            ->select(
                'p.programId,
                            p.entityId,
                            p.country,
                            p.entityId as entity,
                            p.buId as bu,
                            sum(p.marginNet) AS margin,
                            u.id as userId'
            )
            ->join('p.user' , 'u')
            ->orderBy('margin','desc')
            ->addGroupBy('u.id'); // grouped my userId
        // filter
        if (array_key_exists('bu', $paData) && !empty($paData['bu'])) {
            $laBu = explode(',', $paData['bu']);
            $loQuery->andWhere('p.buId in (:bu)')
                ->setParameter('bu', $laBu);
        }
        if (array_key_exists('country', $paData) && !empty($paData['country'])) {
            $laCountry = explode(',', $paData['country']);
            $loQuery->andWhere('p.country in (:country)')
                ->setParameter('country', $laCountry);
        }
        if (array_key_exists('startdate', $paData) && !empty($paData['startdate'])) {
            $loQuery->andWhere('p.dateStart >= :dateStart')
                ->setParameter('dateStart', $paData['startdate']);
        }
        if (array_key_exists('enddate', $paData) && !empty($paData['enddate'])) {
            $loQuery->andWhere('p.dateStart <= :enddate')
                ->setParameter('enddate', $paData['enddate']);
        }
        return $loQuery->getQuery()->getScalarResult();
    } // getStatsByPrograms

    /**
     * get list of available year
     *
     * @return array
     */
    public function getAvailableDates()
    {
        $loQuery = $this->createQueryBuilder('p')
            ->select("p.dateStart as date")
            ->distinct(true)
            ->orderBy('date', 'DESC');
        return $loQuery->getQuery()->getScalarResult();
    } // getAvailableYears
}
