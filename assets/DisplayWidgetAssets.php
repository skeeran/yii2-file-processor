<?php
namespace deanar\fileProcessor\assets;
use yii\web\AssetBundle;

class DisplayWidgetAssets extends AssetBundle
{
	public $sourcePath = '@deanar/fileProcessor/assets';

	public $js = [
	];
	public $css = [
		'css/fileProcessor.css',
	];

    public $depends = [
    ];
}