<?php
/**
 * Created by PhpStorm.
 * Developer: Mikhail Razumovskiy
 * E-mail: rdeanar@gmail.com
 * User: deanar
 * Date: 22/12/14
 * Time: 01:28
 */

namespace deanar\fileProcessor;

use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use deanar\fileProcessor\models\FileStorage;
use deanar\fileProcessor\models\FileSequence;

/**
 * FileProcessor base class
 * @author deanar <rdeanar@gmail.com>
 */
class FileProcessor extends Component
{

    public static function loadVariationsConfig($type)
    {
        // TODO set default variation instead of '_origial'
        $config = Yii::$app->getModule('fp')->variations_config;

        if (!array_key_exists($type, $config)) {
            $return = isset($config['_default']) ? $config['_default'] : array();
        } else {
            $return = $config[$type];
        }

        $all = isset($config['_all']) ? $config['_all'] : array();

        return ArrayHelper::merge($all,$return);
    }

    /**
     * @param array $variationConfig
     * @return array
     */
    public static function normalizeVariationConfig($variationConfig){
        $config = array();
        $arrayIndexed = ArrayHelper::isIndexed($variationConfig);
        $argumentCount = count($variationConfig);
        $defaultMode = Yii::$app->getModule('fp')->default_resize_mod;
        $defaultQuality = Yii::$app->getModule('fp')->default_quality;

        if ($arrayIndexed) {
            $config['width'] = $variationConfig[0];
            $config['height'] = $variationConfig[1];
            if ($argumentCount > 2) {
                $config['mode'] = in_array($variationConfig[2], array('inset', 'outbound')) ? $variationConfig[2] : $defaultMode;
            }
            if ($argumentCount > 3) {
                $config['quality'] = is_numeric($variationConfig[3]) ? $variationConfig[3] : $defaultQuality;
            }

        } else {
            $config['width'] = $variationConfig['width'];
            $config['height'] = $variationConfig['height'];
            $config['mode'] = in_array($variationConfig['mode'], array('inset', 'outbound')) ? $variationConfig['mode'] : $defaultMode;
            if( isset($config['quality']) )
                $config['quality'] =  is_numeric($config['quality']) ? $config['quality']  : $defaultQuality;
            // fill color for resize mode fill in (inset variation)
            //$config['watermark'] = $variationConfig['watermark'];
            // watermark position
            // crop
            // rotate
            // etc
        }

        if (!isset($config['mode']))    $config['mode']    = $defaultMode;
        if (!isset($config['quality'])) $config['quality'] = $defaultQuality;

        return $config;
    }


    public static function getUploads($type, $type_id){
        if (is_null($type_id)) return [];

        return FileSequence::find()
            ->where(['type' => $type, 'type_id' => $type_id])
            ->orderBy('ord')
            ->all();
    }

    public static function getUploadsStack($type, $type_id)
    {
        if (is_null($type_id)) return [];

        $uploads = array();

        $array = self::getUploads($type, $type_id);

        foreach ($array as $item) {
            /**
             * @var $item FileSequence
             */
            $file = $item->getFile();
            array_push($uploads,
                array(
                    'src' => $item->getPublicFileUrl('_thumb'),
                    'type' => $file->mime,
                    'name' => $file->original,
                    'size' => $file->size,
                    'data' => array(
                        'id' => $item->id, // maybe should use $file->id or $item->file_id
                        'type' => $item->type,
                        'type_id' => $item->type_id,
                    )
                ));
        }

        return $uploads;
    }



}
