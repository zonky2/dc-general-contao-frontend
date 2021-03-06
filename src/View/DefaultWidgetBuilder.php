<?php

/**
 * This file is part of contao-community-alliance/dc-general-contao-frontend.
 *
 * (c) 2015 Contao Community Alliance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    contao-community-alliance/dc-general-contao-frontend
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Contao Community Alliance.
 * @license    https://github.com/contao-community-alliance/dc-general-contao-frontend/blob/master/LICENSE LGPL-3.0
 * @filesource
 */

namespace ContaoCommunityAlliance\DcGeneral\ContaoFrontend\View;

use ContaoCommunityAlliance\Contao\Bindings\ContaoEvents;
use ContaoCommunityAlliance\Contao\Bindings\Events\Widget\GetAttributesFromDcaEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\Compatibility\DcCompat;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\DecodePropertyValueForWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\GetPropertyOptionsEvent;
use ContaoCommunityAlliance\DcGeneral\Contao\View\Contao2BackendView\Event\ManipulateWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\ContaoFrontend\Event\BuildWidgetEvent;
use ContaoCommunityAlliance\DcGeneral\Data\ModelInterface;
use ContaoCommunityAlliance\DcGeneral\DataDefinition\Definition\Properties\PropertyInterface;
use ContaoCommunityAlliance\DcGeneral\EnvironmentInterface;

/**
 * Widget Builder to build Contao frontend widgets.
 */
class DefaultWidgetBuilder
{
    /**
     * Handle the build widget event.
     *
     * @param BuildWidgetEvent $event The event.
     *
     * @return void
     */
    public function handleEvent(BuildWidgetEvent $event)
    {
        if ($event->getWidget()) {
            return;
        }

        $widget = $this->buildWidget($event->getEnvironment(), $event->getProperty(), $event->getModel());

        $event->setWidget($widget);
    }

    /**
     * Build a widget for a given property.
     *
     * @param EnvironmentInterface $environment The environment.
     *
     * @param PropertyInterface    $property    The property.
     *
     * @param ModelInterface       $model       The current model.
     *
     * @return \Widget
     */
    public function buildWidget(
        EnvironmentInterface $environment,
        PropertyInterface $property,
        ModelInterface $model
    ) {
        $dispatcher   = $environment->getEventDispatcher();
        $propertyName = $property->getName();
        $propExtra    = $property->getExtra();
        $defName      = $environment->getDataDefinition()->getName();
        $strClass     = $this->getWidgetClass($property);

        $event = new DecodePropertyValueForWidgetEvent($environment, $model);
        $event
            ->setProperty($propertyName)
            ->setValue($model->getProperty($propertyName));

        $dispatcher->dispatch($event::NAME, $event);
        $varValue = $event->getValue();

        $propExtra['required']  = ($varValue == '') && !empty($propExtra['mandatory']);
        $propExtra['tableless'] = true;

        $arrConfig = array(
            'inputType' => $property->getWidgetType(),
            'label'     => array(
                $property->getLabel(),
                $property->getDescription()
            ),
            'options'   => $this->getOptionsForWidget($environment, $property, $model),
            'eval'      => $propExtra,
        );

        if (isset($propExtra['reference'])) {
            $arrConfig['reference'] = $propExtra['reference'];
        }

        $event = new GetAttributesFromDcaEvent(
            $arrConfig,
            $property->getName(),
            $varValue,
            $propertyName,
            $defName,
            new DcCompat($environment, $model, $propertyName)
        );

        $dispatcher->dispatch(ContaoEvents::WIDGET_GET_ATTRIBUTES_FROM_DCA, $event);
        $preparedConfig = $event->getResult();

        $widget = new $strClass($preparedConfig, new DcCompat($environment, $model, $propertyName));

        $widget->currentRecord = $model->getId();

        $event = new ManipulateWidgetEvent($environment, $model, $property, $widget);
        $dispatcher->dispatch(ManipulateWidgetEvent::NAME, $event);

        return $widget;
    }

    /**
     * Try to resolve the class name for the widget.
     *
     * @param PropertyInterface $property The property to get the widget class name for.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    private function getWidgetClass(PropertyInterface $property)
    {
        if (!isset($GLOBALS['TL_FFL'][$property->getWidgetType()])) {
            return null;
        }

        $className = $GLOBALS['TL_FFL'][$property->getWidgetType()];
        if (!class_exists($className)) {
            return null;
        }

        return $className;
    }

    /**
     * Get special labels.
     *
     * @param EnvironmentInterface $environment The environment.
     *
     * @param PropertyInterface    $propInfo    The property for which the options shall be retrieved.
     *
     * @param ModelInterface       $model       The model.
     *
     * @return string[]
     */
    private function getOptionsForWidget(
        EnvironmentInterface $environment,
        PropertyInterface $propInfo,
        ModelInterface $model
    ) {
        $dispatcher = $environment->getEventDispatcher();
        $options    = $propInfo->getOptions();
        $event      = new GetPropertyOptionsEvent($environment, $model);
        $event->setPropertyName($propInfo->getName());
        $event->setOptions($options);
        $dispatcher->dispatch(GetPropertyOptionsEvent::NAME, $event);

        if ($event->getOptions() !== $options) {
            return $event->getOptions();
        }

        return $options;
    }
}
