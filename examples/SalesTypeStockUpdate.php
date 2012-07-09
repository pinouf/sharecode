<?php

namespace LaFourchette\Bundle\ParametersBundle\SalesTypeStock;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

use LaFourchette\Common\API\RestaurantModuleInterface;

use LaFourchette\Common\Component\Exception\InvalidArgumentException;

class SalesTypeStockUpdate
{
    /**
     *
     * pourcentage min stock Promo if not adminLF ( not use again V2 ??... )
     * @var int
     */
    CONST POURCENT_MIN_STOCK = 25;

    /**
     * @var LaFourchette\Common\API\RestaurantModuleInterface
     */
    private $restaurantModule;

    /**
     * @var mixed
     */
    private $logger;

    /**
     * @var Translator
     */
    private $translator;

    /**
     *
     * Trace error for return in js
     * @var array
     */
    private $traceError;

    public function __construct(RestaurantModuleInterface $restaurantModule, Translator $translator, LoggerInterface $logger)
    {
        $this->restaurantModule = $restaurantModule;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->traceError = array(
            "saleType"         => 0,
            "saleTypeTimeSlot" => 0,
        );
    }

    /**
     *
     * Update stock of sales type according the type of fill
     * @param int $idRestaurant
     * @param string $fill
     * @param string $day
     * @param int $idLunConfiguration
     * @param int $idSaleType
     * @param int $time
     * @param int $stock
     * @param saleType[] $saleTypeList
     * @return array $result
     */
    public function updateSaleTypeStock($idRestaurant, $fill, $day, $idLunConfiguration, $idSaleType, $time, $stock, $isOpened, $saleTypeList, $isPromo, $isAdminLF, $saleTypeListAscendant)
    {
        $result = array();
        $result['result'] = 0;
        $stock = intval($stock);

        if ($fill != null && $fill != "" && $day != null && $day != ""
            && $idSaleType != null && $idSaleType != "" && $idLunConfiguration != null && $idLunConfiguration != ""
            && is_int($stock) && $stock > 0
            && is_array($saleTypeList) && !empty($saleTypeList) ) {

            $saleTypeLevelZeroAndOneIds = $this->getSaleTypeLevelZeroAndOneIds($idRestaurant);

            // Récupération des repas avec les stocks
            $lunchConfigurationList = $this->restaurantModule->getLunchConfigurationList(
                array('idRestaurant'    => $idRestaurant),
                array('lunchType'       => 'ASC'),
                array('with_week_lunch' => true)
            );
            $result['result']               = 1;
            $result['idLunchConfiguration'] = $idLunConfiguration;
            $result['changes']              = array();
            $result['changes_timeslot']     = array();

            $currentWeekLunchList = $lunchConfigurationList[$idLunConfiguration]->getWeekLunchList();

            switch ($fill) {
                case "field":
                    $currentWeekLunchListRequest = $this->setSaleTypeStock($currentWeekLunchList, $result, $saleTypeList, $day, $stock, $isOpened, $saleTypeLevelZeroAndOneIds);
                    $currentWeekLunchList        = $currentWeekLunchListRequest['datas'];
                    $result                      = $currentWeekLunchListRequest['result'];

                    break;
                case "week":
                    foreach ($currentWeekLunchList as $curDay => $currentWeekLunch) {
                        $currentWeekLunchListRequest = $this->setSaleTypeStock($currentWeekLunchList, $result, $saleTypeList, $curDay, $stock, $isOpened, $saleTypeLevelZeroAndOneIds);
                        $currentWeekLunchList        = $currentWeekLunchListRequest['datas'];
                        $result                      = $currentWeekLunchListRequest['result'];
                    }

                    break;
                case "field_time":
                    $currentWeekLunchListRequest = $this->setSaleTypeTimeSlotStock($currentWeekLunchList, $result, $idSaleType, $saleTypeList, $saleTypeListAscendant, $day, $time, $stock, $isOpened);
                    $currentWeekLunchList        = $currentWeekLunchListRequest['datas'];
                    $result                      = $currentWeekLunchListRequest['result'];

                    if ($isOpened != null) {
                        // TODO : refactoring ... that's sux
                        $addParamsWhenChangeStReq = $this->addConstrainsParamsWhenChangeStatut($currentWeekLunchList, $result, $idSaleType, $day);
                        $currentWeekLunchList     = $addParamsWhenChangeStReq['datas'];
                        $result                   = $addParamsWhenChangeStReq['result'];
                    }
                    break;
                case "ts_down":
                    $currentWeekSaleTypeTimeSlot = $currentWeekLunchList[$day]->getWeekTimeSlot();
                    foreach ($currentWeekSaleTypeTimeSlot[$idSaleType] as $curTimeSlot => $curTimeSlotData) {
                        $currentWeekLunchListRequest = $this->setSaleTypeTimeSlotStock($currentWeekLunchList, $result, $idSaleType, $saleTypeList, $saleTypeListAscendant, $day, $curTimeSlot, $stock, $isOpened);
                        $currentWeekLunchList        = $currentWeekLunchListRequest['datas'];
                        $result                      = $currentWeekLunchListRequest['result'];
                    }

                    break;
                case "ts_week":
                    foreach ($currentWeekLunchList as $curDay => $currentWeekLunch) {
                        $currentWeekLunchListRequest = $this->setSaleTypeTimeSlotStock($currentWeekLunchList, $result, $idSaleType, $saleTypeList, $saleTypeListAscendant, $curDay, $time, $stock, $isOpened);
                        $currentWeekLunchList        = $currentWeekLunchListRequest['datas'];
                        $result                      = $currentWeekLunchListRequest['result'];
                    }

                    break;
                case "ts_all":
                    foreach ($currentWeekLunchList as $curDay => $currentWeekLunch) {
                        $currentWeekSaleTypeTimeSlot = $currentWeekLunchList[$curDay]->getWeekTimeSlot();
                        foreach ($currentWeekSaleTypeTimeSlot[$idSaleType] as $curTimeSlot => $curTimeSlotData) {
                            $currentWeekLunchListRequest = $this->setSaleTypeTimeSlotStock($currentWeekLunchList, $result, $idSaleType, $saleTypeList, $saleTypeListAscendant, $curDay, $curTimeSlot, $stock, $isOpened);
                            $currentWeekLunchList        = $currentWeekLunchListRequest['datas'];
                            $result                      = $currentWeekLunchListRequest['result'];
                        }
                    }

                    break;
                default:
                    throw new InvalidArgumentException('the fill error', $fill, 'error');
                    break;
            }
        } else {
            throw new InvalidArgumentException('parameters is not valid', 0, 'error');
        }

        // Set the new values stock in object
        $lunchConfigurationList[$idLunConfiguration]->setWeekLunchList($currentWeekLunchList);

        foreach ($lunchConfigurationList as $lunchConfiguration) {
            $this->restaurantModule->saveLunchConfiguration($lunchConfiguration, array("with_week_lunch" => true ));
        }

        $result['traceError'] = $this->traceError;

        return $result;
    }

    /**
     *
     * Set stock of saleTypeTimeSlot in cascade
     * @param weekLunch[] $currentWeekLunchList
     * @param array $result
     * @param int $idSaleType
     * @param string $day
     * @param int $time
     * @param int $stock
     * @return array
     */
    private function setSaleTypeTimeSlotStock($currentWeekLunchList, $result, $idSaleType, $saleTypeList, $saleTypeListAscendant, $day, $time, $stock, $isOpened)
    {
        $currentWeekSaleType         = $currentWeekLunchList[$day]->getWeekSaleType();
        $currentWeekSaleTypeTimeSlot = $currentWeekLunchList[$day]->getWeekTimeSlot();
        $currentIsOpened             = $currentWeekLunchList[$day]->getIsOpened();

        // stock of timeSlot must be inferior or equal at initial stock sale type
        // and the time slot must exit ( all day does not have the same min max hour )
        if (isset($stock) && $stock <= $currentWeekSaleType[$idSaleType]['stock'] && isset($currentWeekSaleTypeTimeSlot[$idSaleType][$time]) == true) {
            $break = !($stock < $currentWeekSaleTypeTimeSlot[$idSaleType][$time]['stock'] || isset($isOpened));

            if (isset($isOpened) && $isOpened ) {
                $saleTypeList = $saleTypeListAscendant;
            }

            foreach ($saleTypeList as $curSaleType) {
                if (!$break) {
                    $idCurSaleType = $curSaleType->getIdSaleType();
                } else {
                    $idCurSaleType = $idSaleType;
                }
                if ($time != null && $time > 0 && $currentWeekSaleTypeTimeSlot[$idCurSaleType][$time]['is_opened']) {
                    // add data in javascript data if the day is open
                    if ( ($currentIsOpened && $currentWeekSaleTypeTimeSlot[$idCurSaleType][$time]['is_opened'] == true) || $isOpened == true ) {
                        // if isOpened exist,so we want to open or close the period
                        if (isset($isOpened)) {
                            // if re open , we take old stock
                            if ($isOpened) {
                                $result['changes_timeslot'][$idCurSaleType][$day][$time] = $currentWeekSaleTypeTimeSlot[$idCurSaleType][$time]['stock'];
                            } else {
                                $result['changes_timeslot'][$idCurSaleType][$day][$time] = null;
                            }
                            // change stock
                        } else {
                            $result['changes_timeslot'][$idCurSaleType][$day][$time] = $stock;
                        }
                    }

                    // set stock
                    if (isset($isOpened)) {
                        $currentWeekSaleTypeTimeSlot[$idCurSaleType][$time]['is_opened'] = $isOpened;
                    } else {
                        $currentWeekSaleTypeTimeSlot[$idCurSaleType][$time]['stock'] = $stock;
                    }
                }
                if ($break) {
                    break;
                }
            }
        } else {
            // we trace the errors, according the count of error
            $result['error_message'] = str_replace(array('#capacity#', '#override_capacity#'), array($currentWeekSaleType[$idSaleType]['stock'], $stock), $this->translator->trans('error_stockSoHigh', array(), 'sales_type_stock'));
            $this->traceError['saleTypeTimeSlot']++;
        }

        // set the new stocks Time Slot in object
        $currentWeekLunchList[$day]->setWeekTimeSlot($currentWeekSaleTypeTimeSlot);

        return array(
            "datas" => $currentWeekLunchList,
            "result" => $result
        );
    }

    /**
     *
     * Set stock of saleType and saleTypeTimeSlot in cascade
     * @param weekLunch[] $currentWeekLunchList
     * @param array $result
     * @param saleType[] $saleTypeList
     * @param string $day
     * @param int $stock
     * @param array $saleTypeLevelZeroAndOneIds
     * @return array
     */
    private function setSaleTypeStock($currentWeekLunchList, $result, $saleTypeList, $day, $stock, $isOpened, $saleTypeLevelZeroAndOneIds)
    {
        $currentWeekSaleType         = $currentWeekLunchList[$day]->getWeekSaleType();
        $currentWeekSaleTypeTimeSlot = $currentWeekLunchList[$day]->getWeekTimeSlot();
        $currentIsOpened             = $currentWeekLunchList[$day]->getIsOpened();

        // use to know that we are trace bug one time ( because there are severals sales type )
        $tmpSaleType = "";

        foreach ($saleTypeList as $curSaleType) {
            $parentStock = $this->isAllowToSetStockSaleTypeWhenStockRestaurantSup($currentWeekSaleType, $saleTypeLevelZeroAndOneIds, $curSaleType, $stock);
            if ($parentStock == -1) {
                // set Stock SaleTypeTimeSlot
                foreach ($currentWeekSaleTypeTimeSlot[$curSaleType->getIdSaleType()] as $curTimeSlot => $curTimeSlotData) {
                    // if stock initial = stock du timeslot(old) or if stock timeslot(old) > new stock
                    if ($currentWeekSaleType[$curSaleType->getIdSaleType()]['stock'] == $currentWeekSaleTypeTimeSlot[$curSaleType->getIdSaleType()][$curTimeSlot]['stock']
                            || $currentWeekSaleTypeTimeSlot[$curSaleType->getIdSaleType()][$curTimeSlot]['stock'] > $stock || isset($isOpened) ) {
                        // add data in javascript data if the day is open
                        if (($currentIsOpened && $currentWeekSaleTypeTimeSlot[$curSaleType->getIdSaleType()][$curTimeSlot]['is_opened'] == true)  || $isOpened == true) {
                            // if isOpened exist,so we want to open or close the period
                            if (isset($isOpened)) {
                                // if re open , we take old stock
                                if ($isOpened) {
                                    $result['changes_timeslot'][$curSaleType->getIdSaleType()][$day][$curTimeSlot] = $currentWeekSaleTypeTimeSlot[$curSaleType->getIdSaleType()][$curTimeSlot]['stock'];
                                } else {
                                    $result['changes_timeslot'][$curSaleType->getIdSaleType()][$day][$curTimeSlot] = null;
                                }
                                // change stock
                            } else {
                                $result['changes_timeslot'][$curSaleType->getIdSaleType()][$day][$curTimeSlot] = $stock;
                            }
                        }

                        // set stock
                        if (isset($isOpened)) {
                            $currentWeekSaleTypeTimeSlot[$curSaleType->getIdSaleType()][$curTimeSlot]['is_opened'] = $isOpened;
                        } else {
                            $currentWeekSaleTypeTimeSlot[$curSaleType->getIdSaleType()][$curTimeSlot]['stock'] = $stock;
                        }
                    }
                }

                // add data in javascript data if the day is open
                if ($currentIsOpened  || $isOpened == true) {
                    $result['changes'][$curSaleType->getIdSaleType()][$day]['stock'] = $stock;
                    // if isOpened exist,so we want to open or close the period
                    if (isset($isOpened)) {
                        // if re open , we take old stock
                        if ($isOpened) {
                            $result['changes'][$curSaleType->getIdSaleType()][$day]['stock'] = $currentWeekSaleType[$curSaleType->getIdSaleType()]['stock'];
                        } else {
                            $result['changes'][$curSaleType->getIdSaleType()][$day]['stock'] = null;
                        }
                        // change stock
                    } else {
                        $result['changes'][$curSaleType->getIdSaleType()][$day]['stock'] = $stock;
                    }
                }

                // set stock saleType before the save
                if (isset($isOpened)) {
                    $currentWeekSaleType[$curSaleType->getIdSaleType()]['is_opened'] = $isOpened;
                } else {
                    $currentWeekSaleType[$curSaleType->getIdSaleType()]['stock'] = $stock;
                }

            } else {
                // we trace the errors, according the count of error
                if ($tmpSaleType == "") {
                    $result['error_message'] = str_replace(array('#capacity#', '#override_capacity#'), array($parentStock, $stock), $this->translator->trans('error_stockSoHigh', array(), 'sales_type_stock'));
                    $tmpSaleType = $curSaleType;
                    $this->traceError['saleType']++;
                }
            }
        }

        // set the new stocks in object
        $currentWeekLunchList[$day]->setWeekSaleType($currentWeekSaleType);
        $currentWeekLunchList[$day]->setWeekTimeSlot($currentWeekSaleTypeTimeSlot);

        return array(
            "datas"  => $currentWeekLunchList,
            "result" => $result
        );
    }

    /**
     *
     * Get saleType level 0 and 1 of restaurant
     * @param int $idRestaurant
     * @return array $saleTypeLevelZeroAndOneIds
     */
    public function getSaleTypeLevelZeroAndOneIds($idRestaurant)
    {
        $saleTypeListAll = $this->restaurantModule->getSaleTypeList(array('idRestaurant' => $idRestaurant));
        $saleTypeLevelZeroAndOneIds = array();

        // get the saleType ids of level 0 and level 1
        foreach ($saleTypeListAll as $theSaleType) {
            if ($theSaleType->getLevel() == "0") {
                $saleTypeLevelZeroAndOneIds[0] = $theSaleType->getIdSaleType();
            } else if ($theSaleType->getLevel() == "1") {
                $saleTypeLevelZeroAndOneIds[1] = $theSaleType->getIdSaleType();
            }
        }

        return $saleTypeLevelZeroAndOneIds;
    }

    /**
     *
     * if stock internet ( level = 1 ) or Stock Promo ( level 2 ), The new Stock must be inferior at Stock Restaurant ( level 1 )
     * @param WeekSaleType $currentWeekSaleType
     * @param array $saleTypeLevelZeroAndOneIds
     * @param int $stock
     * @return boolean
     */
    private function isAllowToSetStockSaleTypeWhenStockRestaurantSup($currentWeekSaleType, $saleTypeLevelZeroAndOneIds, $currentSaleType, $stock)
    {
        if ($currentSaleType->getLevel() == "1" && $currentWeekSaleType[$saleTypeLevelZeroAndOneIds[0]]['stock'] < $stock) {
            return $currentWeekSaleType[$saleTypeLevelZeroAndOneIds[0]]['stock'] ;
        } else if ($currentSaleType->getLevel() == "2" && $currentWeekSaleType[$saleTypeLevelZeroAndOneIds[0]]['stock'] < $stock) {
            return $currentWeekSaleType[$saleTypeLevelZeroAndOneIds[0]]['stock'] ;
        } else if ($currentSaleType->getLevel() == "2" && $currentWeekSaleType[$saleTypeLevelZeroAndOneIds[1]]['stock'] < $stock ) {
            return $currentWeekSaleType[$saleTypeLevelZeroAndOneIds[1]]['stock'] ;
        }

        return -1;
    }

    /**
     * Incomplete function ...( must i compete this method?? V2 .. answer at product .. )
     * If not AdminLF and it is a promo
     * @return boolean
     */
    private function isAllowToSetStockSaleTypeUserType($currentWeekSaleType, $saleTypeLevelZeroAndOneIds, $stock)
    {
        // if AdminLF or not promo ( level = 2 ) => no need to control the stock
        if (!$this->controlStockForPromo) {
            return true;
            // else if promo && not Admin LF
        } else {
            $stockMinimum = ($currentWeekSaleType[$saleTypeLevelZeroAndOneIds[0]]['stock'] * POURCENT_MIN_STOCK) / 100;
        }
    }

    /**
     *
     * Add params timeSlotHaveClose in each WeekSalesType
     * @param LunchConfiguration[] $lunchConfigurationList
     * @param array $options
     * @return LunchConfigurationList
     */
    public function addParamsInWeekLunchSaleType($lunchConfigurationList)
    {
        foreach ($lunchConfigurationList as $idLunchConfiguration => $lunchConfiguration) {
            $weekLunchList = $lunchConfiguration->getWeekLunchList();
            foreach ($weekLunchList as $day => $weekLunch) {
                $weekSaleTypeList = $weekLunch->getWeekSaleType();
                $weekTimeslot = $weekLunch->getWeekTimeslot();
                foreach ($weekSaleTypeList as $idSaleType => $weekSaleType) {
                    $timeSlotHaveClose = false;
                    foreach ($weekTimeslot[$idSaleType] as $timeSlot => $timeSlotData) {
                        // if there are at least one closed , we set the param of weekSaleType
                        if ($timeSlotData['is_opened'] == false) {
                            $timeSlotHaveClose = true;
                            break;
                        }
                    }
                    $weekSaleTypeList[$idSaleType]['timeSlotHaveClose'] = $timeSlotHaveClose;
                }
                $weekLunchList[$day]->setWeekSaleType($weekSaleTypeList);
            }
            $lunchConfigurationList[$idLunchConfiguration]->setWeekLunchList($weekLunchList);
        }

        return $lunchConfigurationList;
    }

    /**
     *
     * When change statut of TimeSlot , 2 things can be change
     * --> add params to delete or add a icon (timeSlotHaveClose)
     * --> This also allow to close WeekSaleType, when all WeekSaleType are closed / to open WeekSaleType, when all WeekSaleType are open
     *
     * @param weekLunch[] $currentWeekLunchList
     * @param array $result
     * @param int $idSaleType
     * @param string $day
     */
    private function addConstrainsParamsWhenChangeStatut($currentWeekLunchList, $result, $idSaleType, $day)
    {
        $weekTimeslot = $currentWeekLunchList[$day]->getWeekTimeSlot();
        $weekSaleType = $currentWeekLunchList[$day]->getWeekSaleType();

        $timeSlotHaveClose = false;
        $isOpenedFirstElem = "";
        $allTimeSlotIsClosed = true;
        $allTimeSlotIsOpen = true;

        foreach ($weekTimeslot[$idSaleType] as $timeSlot => $timeSlotData) {
            if ($timeSlotData['is_opened']) {
                $allTimeSlotIsClosed = false;
            } else {
                $allTimeSlotIsOpen = false;
            }

            if ($timeSlotData['is_opened'] == false) {
                $timeSlotHaveClose = true;
            }
        }

        // if all WeekTimeSlot is Closed => close the WeekSaleType
        if ($allTimeSlotIsClosed) {
            $result['changes'][$idSaleType][$day]['stock'] = null;
            $weekSaleType[$idSaleType]['is_opened'] = 0;
            $currentWeekLunchList[$day]->setWeekSaleType($weekSaleType);
            // if at least one weekTimeSLot is open , and that the weekSaleType is closed => we must open WeekTimeSlot
        } else if (!$allTimeSlotIsClosed && !$weekSaleType[$idSaleType]['is_opened']) {
            $result['changes'][$idSaleType][$day]['stock'] = $weekSaleType[$idSaleType]['stock'];
            $weekSaleType[$idSaleType]['is_opened'] = 1;
            $currentWeekLunchList[$day]->setWeekSaleType($weekSaleType);
        }

        // if there are one weekTimeSLot CLose and that the WeekSaleType is open => we add the icon timeSlotHaveClose
        if ($timeSlotHaveClose && $weekSaleType[$idSaleType]['is_opened']) {
            $result['changes'][$idSaleType][$day]['timeSlotHaveClose'] = true;
            $result['changes'][$idSaleType][$day]['stock'] = $weekSaleType[$idSaleType]['stock'];
            //is All weekTimeSLot is Open and the WeekSaleType is open => we delete the icon timeSlotHaveClose
        } else if ($allTimeSlotIsOpen && $weekSaleType[$idSaleType]['is_opened']) {
            $result['changes'][$idSaleType][$day]['timeSlotHaveClose'] = false;
            $result['changes'][$idSaleType][$day]['stock'] = $weekSaleType[$idSaleType]['stock'];
            // if there are one weekTimeSLot CLose && WeekSaleType close => we delete the icon timeSlotHaveClose
            // the stock is not set before it is already stock at null before
        } else if ($allTimeSlotIsClosed && !$weekSaleType[$idSaleType]['is_opened'] ) {
            $result['changes'][$idSaleType][$day]['timeSlotHaveClose'] = false;
        }

        return array(
            "datas"  => $currentWeekLunchList,
            "result" => $result
        );
    }
}
