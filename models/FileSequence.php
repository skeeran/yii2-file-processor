<?php

namespace deanar\fileProcessor\models;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use deanar\fileProcessor\FileProcessor;
use deanar\fileProcessor\models\FileStorage;

use Imagine\Gd\Imagine;
//use Imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;
use Imagine\Exception\Exception;

/**
 * This is the model class for table "fp_file_sequence".
 *
 * @property integer $id
 * @property string $type
 * @property integer $type_id
 * @property integer $file_id
 * @property string $hash
 * @property integer $ord
 */
class FileSequence extends \yii\db\ActiveRecord
{

    public function init(){
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%fp_file_sequence}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type_id', 'ord', 'file_id'], 'integer'],
            [['type', 'hash'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'type' => Yii::t('app', 'Тип'),
            'type_id' => Yii::t('app', 'ID Типа'),
            'file_id' => Yii::t('app', 'ID Файла'),
            'hash' => Yii::t('app', 'HASH'),
            'ord' => Yii::t('app', 'Порядок отображения'),
        ];
    }

    /**
     * @return FileStorage
     */
    public function getFile()
    {
        return $this->hasOne(FileStorage::className(), ['id' => 'file_id'])->one();
    }



    /*
     * File upload and process methods
     *
     */


    public function attachFile($file, $type, $type_id, $hash){

        if($file instanceof FileStorage){
            $this->file_id = $file->id;
        }else{
            $this->file_id = $file;
        }

        $this->type = $type;
        $this->type_id = $type_id;
        $this->hash = $hash;
        $this->ord = FileSequence::getMaxOrderValue($type, $type_id, $hash) + 1;
        $this->save();

        return $this;
    }

    /**
     * @param $id
     * @return bool
     *
     * Static removeFile (reference to FileStorage)
     */
    public static function staticRemoveFile($id, $check){

        /**
         * @var $file FileSequence
         */
        $file = self::findOne($id);
        if(
            $check['type']    == $file->type &&
            $check['type_id'] == $file->type_id
        ){
            return $file->getFile()->removeFile();
        }
        return false;
    }

    /**
     * @param $type
     * @param $type_id
     * @param $hash
     * @return int|mixed
     */
    public static function getMaxOrderValue($type, $type_id, $hash)
    {
        if (is_null($type) || is_null($type_id)){
            $where = ['hash' => $hash];
        }else{
            $where = ['type' => $type, 'type_id' => $type_id];
        }

        $find =  self::find()
            ->select('MAX(ord) as ord')
            ->where($where)
            ->one();

        return is_null($find) ? 0 : $find->ord;
    }


    /**
     * @param string $variation
     * @return string
     *
     * Get upload path to file
     */
    public function getUploadFilePath($variation='original'){
        return $this->getFile()->getUploadFilePath($variation);
    }


    /**
     * @param string $variation
     * @param boolean $absolute
     * @return string
     *
     * Get Public file url
     */
    public function getPublicFileUrl($variation='original', $absolute=false){
        return $this->getFile()->getPublicFileUrl($variation, $absolute);
    }

    /**
     * @param string $variation
     * @param bool $absolute
     * @param array $options
     * @return string
     */
    public function imgTag($variation='original', $absolute=false,$options=array()){
        return $this->getFile()->imgTag($variation, $absolute, $options);
    }

}
