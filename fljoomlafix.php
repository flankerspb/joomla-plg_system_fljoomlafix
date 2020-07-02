<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.fljoomlafix
 *
 * @author      Vitaliy Moskalyuk  <info@2sweb.ru>
 * @copyright   Copyright © 2018 Vitaliy Moskalyuk. All rights reserved.
 * @license     GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die();

// echo '<pre>';
// print_r(JLoader::getClassList());
// echo '</pre>';
// die();


// $app = &JFactory::getApplication();
// if ($app->isClient('site'))
// {
	// // $doc = &JFactory::getDocument();
	// // $doc->isHome = true;
	// $app->isHome = true;
	// var_dump($app);
// }

class PlgSystemFlJoomlaFix extends JPlugin
{
	protected $app;
	
	protected $isSite;
	
	protected $isAdmin;
	
	protected $isHTML = false;
	
	public function __construct(&$subject, $config = array())
	{
		// Defines.
		define('FLPATH_OVERRIDES', __DIR__ . '/overrides');
		
		parent::__construct($subject, $config);
		
		$this->isSite = $this->app->isClient('site');
		$this->isAdmin = !$this->isSite;
		$this->isHTML = (JFactory::getDocument()->getType() == 'html');
	}
	
	public function onContentPrepareForm($form, $data)
	{
		if (!$this->isAdmin || !($form instanceof JForm) || $form->getName() != 'com_plugins.plugin' || !$form->getField('fljoomlafix'))
		{
			return;
		}
		
		$overrides = self::getFiles('overrides')['php'];
		
		$overrides_path = $this->params->get('overrides_path', 'plugin');
		
		switch ($overrides_path)
		{
			case 'root':
				$path = JPATH_ROOT;
				break;
			default:
				$path = __DIR__;
				break;
		}
		
		$xml = '<?xml version="1.0" encoding="UTF-8"?><form>';
		
		$xml .= '<fields name="params"><fieldset name="overrides">';
		
		$xml .= '<field type="note" label="PLG_FLJOOMLAFIX_SAVE_PLUGIN" description="PLG_FLJOOMLAFIX_SAVE_PLUGIN_DESC" showon="overrides_path!:' . $overrides_path . '" class="alert alert-info"/>';
		
		$xml .= '<field type="note" label="Classes"/>';
		
		$xml .= '<fields name="override_classes">';
		
		foreach ($overrides as $file)
		{
			if(file_exists($path.$file))
			{
				$class = self::getClassName($path.$file);
				
				$xml .= '<fields name="' . $class . '">';
				
				$xml .= '<field type="radio" name="enable" label="' . $class . '" description="' . $file . '" class="btn-group btn-group-yesno" default="1"><option value="0">JNO</option><option value="1">JYES</option></field>';
				
				$xml .= '<field name="file" type="hidden" default="' . $file . '"/>';
				
				$xml .= '</fields>';
			}
		}
		
		$xml .= '</fields></fieldset>';
		
		$assets = self::getFiles('assets');
		
		$xml .= '<fieldset name="assets"><fields name="assets">';
		
		foreach ($assets as $type => $files)
		{
			
			$xml .= '<field type="note" label="'.$type.'"/>';
			
			$xml .= '<fields name="' . $type . '">';
			
			$i = 0;
			
			foreach ($files as $file)
			{
				
				$pathinfo = pathinfo($file);
			
				$xml .= '<fields name="' . $i . '">';
				
				$xml .= '<field type="radio" name="enable" label="' . $pathinfo['basename'] . '" description="' . $file . '" class="btn-group btn-group-yesno" default="1"><option value="0">JNO</option><option value="1">JYES</option></field>';
				
				$xml .= '<field name="file" type="hidden" default="' . $file . '"/>';
				
				$xml .= '</fields>';
				
				$i++;
			}
			
			$xml .= '</fields>';
		}
		
		$xml .= '</fields></fieldset>';
		
		$xml .= '</fields></form>';
		
		$form->load($xml);
	}
	
	public function onAfterInitialise()
	{
		// Register overrided classes
		if(!$this->params->get('overrides_enable', '0'))
			return;
		
		$path = $this->params->get('overrides_path', 'plugin');
		
		switch ($path)
		{
			case 'root':
				$path = JPATH_ROOT;
				break;
			default:
				$path = __DIR__;
				break;
		}
		
		$classes = $this->params->get('override_classes', array());
		
		foreach ($classes as $class => $value)
		{
			if($value->enable && file_exists($path.$value->file))
			{
				JLoader::register($class, $path.$value->file, true);
			}
		}
	}
	
	public function onBeforeCompileHead()
	{
		// Fixes admin panel template
		if (!$this->isAdmin || !$this->isHTML)
		{
			return;
		}
		
		$document = JFactory::getDocument();
		
		$assets = $this->params->get('assets', array());
		
		$uri = JUri::root() . 'plugins/system/fljoomlafix';
		
		foreach($assets as $type => $array)
		{
			foreach($array as $asset)
			{
				if($asset->enable && file_exists(__DIR__ . $asset->file))
				{
					switch ($type)
					{
						case 'css':
							$document->addStyleSheet($uri . $asset->file);
							break;
						case 'js':
							$document->addScript($uri . $asset->file);
							break;
						default:
							break;
					}
				}
			}
		}
	}
	
	private static function getFiles($target, &$list = [])
	{
		$folder = __DIR__ . '/' . $target . '/';
		
		$targets = array();
		
		$values = scandir($folder);
		
		sort($values, SORT_NATURAL | SORT_FLAG_CASE);
		
		foreach($values as $value)
		{
			if($value == '.' || $value == '..' )
				continue;
			
			$file = $folder . $value;
			
			if(is_file($file))
			{
				$pathinfo = pathinfo($value);
				$ext = strtolower($pathinfo['extension']);
				$f = '/' . $target . '/' . $value;
				
				$list[$ext][] = $f;
			}
			else if(is_dir($file))
			{
				$targets[] = $target . '/' . $value;
			}
		}
		
		if(count($targets))
		{
			foreach($targets as $value)
			{
				self::getFiles($value, $list);
			}
		}
		
		return $list;
	}
	
	private static function getClassName($path)
	{
		//Grab the contents of the file
		$contents = file_get_contents($path);

		//Start with a blank namespace and class
		$namespace = $class = "";

		//Set helper values to know that we have found the namespace/class token and need to collect the string values after them
		$getting_namespace = $getting_class = false;

		//Go through each token and evaluate it as necessary
		foreach (token_get_all($contents) as $token) {

				//If this token is the namespace declaring, then flag that the next tokens will be the namespace name
				if (is_array($token) && $token[0] == T_NAMESPACE) {
						$getting_namespace = true;
				}

				//If this token is the class declaring, then flag that the next tokens will be the class name
				if (is_array($token) && $token[0] == T_CLASS) {
						$getting_class = true;
				}

				//While we're grabbing the namespace name...
				if ($getting_namespace === true) {

						//If the token is a string or the namespace separator...
						if(is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
								
								//Append the token's value to the name of the namespace
								$namespace .= $token[1];

						}
						else if ($token === ';') {

								//If the token is the semicolon, then we're done with the namespace declaration
								$getting_namespace = false;

						}
				}

				//While we're grabbing the class name...
				if ($getting_class === true) {

						//If the token is a string, it's the name of the class
						if(is_array($token) && $token[0] == T_STRING) {

								//Store the token's value as the class name
								$class = $token[1];

								//Got what we need, stope here
								break;
						}
				}
		}

		//Build the fully-qualified class name and return it
		return $namespace ? $namespace . '\\' . $class : $class;
	}
}
