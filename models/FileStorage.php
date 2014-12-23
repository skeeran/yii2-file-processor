<?php

namespace deanar\fileProcessor\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use deanar\fileProcessor\FileProcessor;
use deanar\fileProcessor\models\FileSequence;

use Imagine\Gd\Imagine;
//use Imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;
use Imagine\Exception\Exception;

/**
 * This is the model class for table "fp_file_storage".
 *
 * @property integer $id
 * @property string $timestamp
 * @property string $filename
 * @property string $original
 * @property string $mime
 * @property integer $size
 * @property integer $width
 * @property integer $height
 */
class FileStorage extends \yii\db\ActiveRecord
{
    public $filename_separator = '_';
    public $upload_dir = '';    // override in init
    public $default_quality;    // override in init
    public $default_resize_mod; // override in init
    public $unlink_files;       // override in init

    public $type = 'default'; // fast fix


    public function init(){
        $this->upload_dir           = Yii::$app->getModule('fp')->upload_dir;
        $this->default_quality      = Yii::$app->getModule('fp')->default_quality; // maybe already unused
        $this->default_resize_mod   = Yii::$app->getModule('fp')->default_resize_mod; // maybe already unused
        $this->unlink_files         = Yii::$app->getModule('fp')->unlink_files;
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fp_file_storage}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['filename', 'original', 'size'], 'required'],
            [['timestamp'], 'safe'],
            [['size', 'width', 'height'], 'integer'],
            [['filename', 'original', 'mime'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'timestamp' => Yii::t('app', 'Время загрузки'),
            'filename' => Yii::t('app', 'Имя файла'),
            'original' => Yii::t('app', 'Оригинальное имя файла'),
            'mime' => Yii::t('app', 'Тип файла'),
            'size' => Yii::t('app', 'Размер файла'),
            'width' => Yii::t('app', 'Ширина'),
            'height' => Yii::t('app', 'Высота'),
        ];
    }

    /*
     * File upload and process methods
     *
     */

    public function isImage(){
        return !is_null($this->width);
    }

    /**
     * @return bool
     *
     * Remove file from file system and database (original + all variations)
     */
    public function removeFile(){
        $config = FileProcessor::loadVariationsConfig($this->type);
        $error = false;

        if ($this->unlink_files) {
            foreach ($config as $variation_name => $variation_config) {

                if ($variation_name == '_original') {
                    if (!$variation_config) continue;
                    $variation_name = 'original';
                }

                if (substr($variation_name, 0, 1) !== '_' || $variation_name == '_thumb') {
                    // delete file
                    $file = $this->getUploadFilePath($variation_name);
                    if (file_exists($file)) {
                        if (!@unlink($file)) {
                            echo 'Error unlinking file: ' . $file;
                            $error = true;
                        } else {
                            echo 'Unlink file: ' . $file . '' . PHP_EOL;
                        }
                    }
                }
            }
        }

        if(!$error){
            return $this->delete() ? true : false;
        }
        return false;
    }

    /**
     * Upload file from temp place + generate all variations (for images)
     * @param $file_temp_name
     * @param null $config
     * @return bool
     */
    public function process($file_temp_name, $config=null){
        if( is_null($config) ) $config = FileProcessor::loadVariationsConfig($this->type);

        $is_image = $this->isImage();

        if ( !$is_image || ( isset($config['_original']) && $config['_original'] === true ) ){


            $upload_dir = $this->getUploadDir('default'); // $this->type


            if(!is_dir($upload_dir) ) mkdir($upload_dir, 0777, true); // TODO maybe add yii function for creating dirs

            $upload_full_path = $upload_dir . DIRECTORY_SEPARATOR . $this->filename;

            if (move_uploaded_file($file_temp_name, $upload_full_path)) {
                // cool
            }
        }else{
            $upload_full_path = $file_temp_name;
        }

        if(!$is_image) return true;


        try {
            $imagine = new Imagine();

            /*
            $imagine = new Imagine\Gd\Imagine();
            $imagine = new Imagine\Imagick\Imagine();
            $imagine = new Imagine\Gmagick\Imagine();
            */

            $image = $imagine->open($upload_full_path);

            foreach ($config as $variation_name => $variation_config) {
                if (substr($variation_name, 0, 1) !== '_' || $variation_name == '_thumb') {
                    $this->makeVariation($image, $variation_name, $variation_config);
                }
            }
        } catch (Imagine\Exception\Exception $e) {
            // handle the exception
        }

    } // end of process



    /**
     * @param $image
     * @param $variationName
     * @param $variationConfig
     * @return bool
     *
     * Resize images by variation config
     */
    public function makeVariation($image, $variationName, $variationConfig){
        if( !is_array($variationConfig)) return false;

        $config = FileProcessor::normalizeVariationConfig($variationConfig);

        // here because in normalizeVariationConfig we don't process variation name
        if($variationName == '_thumb'){
            $config['mode'] = 'outbound';
        }

        if($config['mode'] == 'inset'){
            $mode = ImageInterface::THUMBNAIL_INSET;
        }else{
            $mode = ImageInterface::THUMBNAIL_OUTBOUND;
        }

        $image = $image->thumbnail(new Box($config['width'], $config['height']), $mode);

        $options = array(
            'quality' => $config['quality'],
        );

        $image->save( $this->getUploadFilePath( $variationName ) , $options );
    }

    /**
     * @param $type
     * @return bool|string
     *
     * Get upload dir
     */
    public function getUploadDir($type){
        return  Yii::getAlias('@app/web/'.$this->upload_dir.'/' . $type);
    }


    /**
     * @param string $variation
     * @return string
     *
     * Get upload path to file
     */
    public function getUploadFilePath($variation='original'){
        return $this->getUploadDir($this->type) . DIRECTORY_SEPARATOR . $this->getFilename($variation);
    }


    /**
     * @param string $variation
     * @param boolean $absolute
     * @return string
     *
     * Get Public file url
     */
    public function getPublicFileUrl($variation='original', $absolute=false){
        return Url::base($absolute) . '/' . $this->upload_dir . '/' . $this->type . '/' . $this->getFilename($variation);
    }

    /**
     * @param string $variation
     * @return string
     *
     *  Get variation filename
    */
    public function getFilename($variation='original'){
        if(empty($this->filename)) return '';
        //TODO make file name template
        if($variation == 'original'){
            return $this->filename;
        }else{
            return $variation . $this->filename_separator . $this->filename;
        }
    }


    public static function extractExtensionName($filename){
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    /**
     * @param $filename
     * @return string
     *
     * Generate unique filename by uniqid() and original extension
     */
    public static function generateBaseFileName($filename){
        //TODO perhaps check extension and mime type compatibility
        return uniqid().'.'.self::extractExtensionName($filename);
    }


    public function imgTag($variation='original', $absolute=false,$options=array()){
        //TODO return 'empty' value if no image available
        if( empty($this->filename) ) return '';
        $src = $this->getPublicFileUrl($variation,$absolute);
        $attributes = ['src' => $src];
        return Html::tag('img', '', ArrayHelper::merge($options, $attributes));
    }


    //TODO move to helper class
    /**
     * Converts php.ini style size to bytes
     *
     * @param string $sizeStr $sizeStr
     * @return int
     */
    public static function sizeToBytes($sizeStr)
    {
        // used decimal, not binary
        $kilo = 1000;
        switch (substr($sizeStr, -1)) {
            case 'M':
            case 'm':
                return (int) $sizeStr * $kilo * $kilo;
            case 'K':
            case 'k':
                return (int) $sizeStr * $kilo;
            case 'G':
            case 'g':
                return (int) $sizeStr * $kilo * $kilo * $kilo;
            default:
                return (int) $sizeStr;
        }
    }
}
