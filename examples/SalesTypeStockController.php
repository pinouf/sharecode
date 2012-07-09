<?php

namespace LaFourchette\Bundle\ParametersBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use LaFourchette\Bundle\ParametersBundle\Form\LunchSaleTypeStockType;
use LaFourchette\Common\Component\Exception\InvalidArgumentException;

/**
* @Route("/parameters/salesTypeStock")
*/
class SalesTypeStockController extends Controller
{
    /**
    * Affichage des lunch configurations et des stocks associés
    * @Route("/index", name="parameters_salesTypeStock_index")
    * @Template()
    */
    public function salesTypeStockAction()
    {
        $this->get('user_role.checker')->process('RR_STOCK_CONFIG_SALE_TYPE_STOCK_WEEK');

        $token        = $this->get('security.context')->getToken();
        $user         = $token->getUser();
        $request      = $this->container->get('request');
        $translator   = $this->container->get('translator');
        $idRestaurant = $user->getCurrentIdRestaurant();

        $idLunchConfigurationShow = $request->get("id");

        // Récupération des repas avec les stocks
        $lunchConfigurationList = $this->container->get('module.restaurant')->getLunchConfigurationList(
            array('idRestaurant'    => $user->getCurrentIdRestaurant()),
            array('lunchType'       => 'ASC'),
            array('with_week_lunch' => true)
        );

        // récupération des types de ventes
        $saleTypeList = $this->container->get('module.restaurant')->getSaleTypeList(array(
            'idRestaurant' => $idRestaurant
        ), array(), array(
            'with_menu' => true,
            'with_special_offer' => true,
        ));
        $saleTypeList = $this->get('business.sale_type')->sortSaleTypeList($saleTypeList);

        // récupération du restaurant courant pour la configuration + form
        $restaurant = $this->container->get("module.restaurant")->getRestaurant($user->getCurrentIdRestaurant(), array(
            'with_restaurant_options' => true,
            'with_restaurant_detail' => true,
            'with_restaurant_configuration_step'=>true
        ));
        $restaurantConfigurationStep = $restaurant->getRestaurantConfigurationStep();
        $restaurantConfigurationStep->setConfigCapacity(true);
        $this->get('business.restaurant')->saveRestaurantConfigurationStep($restaurantConfigurationStep);
        $this->container->get('memcache')->delete('RestaurantConfigurationStep.' . $idRestaurant);
        $oldCapacity = $restaurant->getRestaurantOptions()->getCapacity();

        $form = $this->createForm(new LunchSaleTypeStockType(), $restaurant);

        if ($request->getMethod() == 'POST') {
            $this->get('session')->removeFlash('error');
            $this->get('session')->removeFlash('notice');
            $form->bindRequest($request);

            if ($form->isValid()) {
                $newCapacity = $restaurant->getRestaurantOptions()->getCapacity();
                $this->container->get("module.restaurant")->saveRestaurant($restaurant, array('with_restaurant_options' => true, 'with_restaurant_detail' => true));
                if ($newCapacity != $oldCapacity) {
                    $this->get('business.lunch_configuration')->updateLunchConfigurationListCapacities($lunchConfigurationList, $oldCapacity, $newCapacity);
                }
                $this->container->get('session')->setFlash('notice', 'notice.success');
            } else {
                $this->container->get('session')->setFlash('error', 'notice.failure');
            }
        }

        $lunchConfigurationList      = $this->container->get("salesTypeStock.update")->addParamsInWeekLunchSaleType($lunchConfigurationList);
        $timeSlotMinMax              = $this->container->get("module.restaurant")->getTimeSlotMinMaxOfLunchConfigurationList($lunchConfigurationList);
        $isAllowToSetStockNewsLetter = $this->container->get('business.restaurant')->isInternetStockConfigurationAllowedConsideringNewsLetter($idRestaurant, $user->isAdminLF());

        return array(
            'idLunchConfigurationShow'    => $idLunchConfigurationShow,
            'lunchConfigurationList'      => $lunchConfigurationList,
            'saleTypeList'                => $saleTypeList,
            'timeSlotMinMax'              => $timeSlotMinMax,
            'isAllowToSetStockNewsLetter' => $isAllowToSetStockNewsLetter,
            'form'                        => $form->createView()
        );

        return new Response($content);
    }

    /**
    * @Route("/edit", name="parameters_salesTypeStock_edit", defaults={"_format"="json"})
    * @Template()
    */
    public function editSalesTypeStockAjaxAction()
    {

        $this->get('user_role.checker')->process('RR_STOCK_CONFIG_SALE_TYPE_STOCK_WEEK');

        $token        = $this->get('security.context')->getToken();
        $user         = $token->getUser();
        $request      = $this->container->get('request');
        $idRestaurant = $user->getCurrentIdRestaurant();

        $fill                 = $request->get('fill');
        $day                  = $request->get('day');
        $idLunchConfiguration = $request->get('idLunchConfiguration');
        $idSaleType           = $request->get('idSaleType');
        $time                 = $request->get('time');
        $stock                = $request->get('stock');
        $isOpened             = $request->get('isOpened');
        $isPromo              = false;
        $isAdminLF            = $user->isAdminLF();

        $saleType = $this->container->get('module.restaurant')->getSaleType(array('idSaleType' => $idSaleType ));

        switch ($saleType->getLevel()) {
            case "0":
                $saleTypeList = $this->container->get('module.restaurant')->getSaleTypeList(array('idRestaurant' => $idRestaurant));
                $saleTypeListAscendant = $saleTypeList;
                break;
            case "1":
                $saleTypeList = $this->container->get('module.restaurant')->getSaleTypeList(array('idRestaurant' => $idRestaurant), array(), array(), array("level" => 0));
                $saleTypeListAscendant = $this->container->get('module.restaurant')->getSaleTypeList(array('idRestaurant' => $idRestaurant), array(), array(), array("level" => 2));
                break;
            case "2":
                $saleTypeList = $this->container->get('module.restaurant')->getSaleTypeList(array('idRestaurant' => $idRestaurant, "idSaleType" => $idSaleType));
                $saleTypeListAscendant = $this->container->get('module.restaurant')->getSaleTypeList(array('idRestaurant' => $idRestaurant), array(), array(), array("level" => 2)) + $saleTypeList;

                if (($saleType->getIdSpecialOffer() != null && $saleType->getIdSpecialOffer() > 0) || ( $saleType->getIdMenu() != null && $saleType->getIdMenu() > 0)) {
                    $isPromo = true;
                }
                break;
        }
        $result = array();
        $result = $this->container->get("salesTypeStock.update")->updateSaleTypeStock($idRestaurant, $fill, $day, $idLunchConfiguration, $idSaleType, $time, $stock, $isOpened, $saleTypeList, $isPromo, $isAdminLF, $saleTypeListAscendant);

        return array(
            'result' => json_encode($result)
        );

    }

    /**
     * Return if there are reservations
     * @Route("/getIfResaExistDayOfTimeSlotAjax", name="parameters_getIfResaExistDayOfTimeSlotAjax")
     * @Template()
     */
    public function getIfResaExistDayOrTimeSlotAjaxAction()
    {
        $this->get('user_role.checker')->process('RR_STOCK_CONFIG_SALE_TYPE_STOCK_WEEK');

        $token   = $this->get('security.context')->getToken();
        $user    = $token->getUser();
        $request = $this->container->get('request');

        $idLunchConfiguration = $request->get("idLunchConfiguration");
        $day                  = $request->get("day");
        $timeSlot             = $request->get("timeSlot");
        $elemId               = $request->get("elemId");

        $result           = array();
        $result['result'] = 0;
        $result['elemId'] = $elemId;

        if (isset($idLunchConfiguration) && $idLunchConfiguration > 0 && isset($day) && $day != "" ) {
            $reservationInClosingPeriodRequest = $this->container->get('business.reservation')->getReservationList(array(
                'viewable_by' => $user,
                'options'     => array(
                    'day'                  => $day,
                    'timeSlot'             => $timeSlot,
                    'withResaValid'        => true,
                    'withResaInFuture'     => true,
                    'idLunchConfiguration' => $idLunchConfiguration
                ),
            ));
            $nbResa = $reservationInClosingPeriodRequest['nbElem'];

            // if no reservation
            if ($nbResa == 0) {
                $result['result'] = 1;
            }
        }

        return array(
            'result' => json_encode($result)
        );
    }
}
