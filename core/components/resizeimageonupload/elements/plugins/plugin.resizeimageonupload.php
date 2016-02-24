<?php
/**
 * ResizeImageOnUpload Plugin updated by Setest

 * Author: Stepan Prishepenko (Setest) <itman116@gmail.com> (01.04.2013)

 * Version: 1.1.3 (06.05.2014) fix errors, добавил параметр auto_alias, производит автоматическую
 * транслитерацию имен файлов, нормально срабатывает только после отработки плагина транслитерации,
 * т.к. событие срабатывает тогда когда файлы у нас уже будут загружены
 *
 * Version: 1.1.2 (08.04.2013) fix errors
 * Version: 1.1.1 (05.04.2013) fix errors
 * Version: 1.1.0 (03.04.2013) fix some errors, add new parameter "exclude_dirs_suffix"
 * Version: 1.0.0 (01.04.2013) It`s must correctly work in ModX {REVO} 2.2 - 2.2.6

 * Events: OnFileManagerUpload
 * Required: PhpThumbOf snippet for resizing images

 * Based on: Vasiliy Naumkin (04.08.2012) <bezumkin@yandex.ru>  http://bezumkin.ru/blog/2012/08/04/resizeonupload/
 * Edited by Aleksey Naumov (08.10.2012)  http://www.createit.ru/blog/modx/2012/plugin-resizeimageonupload-modx-revo/
 * Good idea by Mordynsky Nicholay (create storage thumbs folder) (28.03.2013)



	Плагин обладает следующими параметрами и их возможностями:
	create_thumbs - при ДА создает привью изображений
	thumbnail_dir - если указано, то создает директорию, родителем которой является base_path источника, в которой хранятся превью изображений.
					Точки из названия папки удаляются.
	thumb_key	  - Ключ добавляющийся в имя файла предпросмотра.
	default_thumb_path - Если параметр указан, то игнорируется параметр "thumbnail_dir" и все файлы превью загружаются в эту директорию.

	default_src_param - параметры исходного изображения по умолчанию, задаются в соответствии с правилами PhpThumbOf
						пример: 'w'=800,'h'=600,'zc'=0,'q'=80,'fltr'='wmi|/assets/img/watermark.png|BR|100|0|0'
						можно и так: w=800,h=600,zc=0,q=80,fltr=wmi|/assets/img/watermark.png|BR|100|0|0
	default_thumb_param - параметры thumbs изображения по-умолчанию

	log - при == true создается лог файл с именем плагина

	Исключения - условия при которых плагин прерывает работу:
	exclude_dirs - путь к директориям разделенне запятыми, в которых плагин работать не будет
	exclude_dirs_children - при ДА распростраянет действие исключения родителя на дочерние директории
	exclude_part_of_name - если имя загружаемого файла содержит текст из данного параметра, то плагин прекращает работу
	exclude_sources - источники изображения при использовании которых плагин прекращает работу,
					  принимает имя источника или его id перечисленные через запятую.
					  Будьте внимательны при вводе имени источника, следите за регистром букв.
					  пример: Images, 5, hohoho, tetki

 */


// проверяем нужное событие
// return;
if ($modx->event->name != 'OnFileManagerUpload') {return;}

$modx->lexicon->load('resizeimageonupload:default');

$log = $modx->getOption('log', $scriptProperties,false);
////////////////////////////////--=LOG=--///////////////////////////////////
if ($log){
	// error_reporting(E_ALL);
	// ini_set('display_errors',1);

	$logFileName = $modx->event->activePlugin;
	$modx->setLogLevel(modX::LOG_LEVEL_INFO);
		$date = date('Y-m-d____H-i-s');  // использовать в выводе даты : - нельзя, иначе не создается лог в файл
		$modx->setLogTarget(array(
		   'target' => 'FILE',
		   'options' => array('filename' => "{$logFileName}_$date.log")
		));
	$start_time_global = microtime(true); //общее время выполнения скрипта
}
////////////////////////////////--=LOG=--///////////////////////////////////


if (!class_exists('fileAction')) {
	class fileAction{
		function __construct(&$modx) {
				$this->modx = $modx;
		}
		function remove($filename=false){
			if (!$filename) return false;
			$this->modx->log(modX::LOG_LEVEL_INFO, $info);
			//http://rtfm.modx.com/display/revolution20/modFileHandler
	    $this->modx->getService('fileHandler','modFileHandler');
	    $fileToBeRemoved = $this->modx->fileHandler->make($filename);
	    if (!is_file($filename) || !($fileToBeRemoved instanceof modFile)) return ('File not exist!');

	    if (!$fileToBeRemoved->remove()) {
	       return 'Could not remove file.';
	    }
		}
	}
};
$fileAction = new fileAction($modx);





// подключаем phpthumb
require_once MODX_CORE_PATH.'model/phpthumb/phpthumb.class.php';

// сдесь вы можете указать особые директории в которых будут особые параметры
// загрузки изображений
// $config = array(
 // 'assets/upload/folder1/' => array(
   // 'src' => array('w' => 800,'h' => 600,'zc' => 1,'bg' => '#fff','q' => 90),
 // ),
 // 'assets/upload/folder2/' => array(
   // 'src' => array('w' => 400,'h' => 300,'zc' => 1,'bg' => '#fff','q' => 90),
   // 'thumb' =>array('w' => 200,'h' => 150,'zc' => 1,'bg' => '#fff','q' => 70),
 // ),
// );

$config_default_src_param = $modx->getOption('default_src_param', $scriptProperties, "'w'=1280,'h'=1024,'zc'=0,'q'=80");
$config_default_thumb_param = $modx->getOption('default_thumb_param', $scriptProperties, "'w'=200,'h'=150,'zc'=1,'bg'='#fff','q'=70");
// $config_default_src_param = "w=600,zc=0,q=80,fltr=wmi|/assets/templates/new/img/others/logo_watermark.png|BR|100|0|0";
// $config_default_thumb_param = "'w'=200,'h'=150,'zc'=1,'bg'='#fff','q'=70";


$auto_alias = $modx->getOption('auto_alias', $scriptProperties, true);	// производит автоматическую транслитерацию имен файлов

$create_thumbs = $modx->getOption('create_thumbs', $scriptProperties, false);
$thumbnail_dir = $modx->getOption('thumbnail_dir', $scriptProperties);
$thumbnail_dir = str_replace('//', '/', $thumbnail_dir);
$thumbnail_dir = str_replace(array("..","."), "", $thumbnail_dir);
$default_thumb_path = $modx->getOption('default_thumb_path',$scriptProperties,null); // assets/images/thumbs
$thumb_key = $modx->getOption('thumb_key',$scriptProperties,''); // this parameter add in the filename of thumbnail

$exclude_dirs = $modx->getOption('exclude_dirs', $scriptProperties, null);	// папки исключения
$exclude_dirs_suffix = $modx->getOption('exclude_dirs_suffix', $scriptProperties, "{ExChild}");	// суффикс папки исключения, при наличии, которого дочерние папки исключаются
$exclude_dirs_children = $modx->getOption('exclude_dirs_children', $scriptProperties, null);	// исключать ли дочерние директории?

$exclude_part_of_name = $modx->getOption('exclude_part_of_name', $scriptProperties, null);	// исключаем при наличии в имени файла этого текста
$exclude_sources = $modx->getOption('exclude_sources', $scriptProperties, null);	// исключаем по источникам.  Можно использовать id и имя источника, будьте внимательны при вводе имени источника, следите за регистром букв.

$exclude_extensions = $modx->getOption('exclude_extensions', $scriptProperties, null);	// исключаем файлы с раширением ... содержащиеся в exclude_extensions, перечисленные через запятую

// перестрахуемся и удалим лишние кавычки
$config_default_src_param = str_replace(array("'"," "),"",$config_default_src_param);
$config_default_thumb_param = str_replace(array("'"," "),"",$config_default_thumb_param);

// 'w'=580,'h'=324,'zc'=0,'q'=80,'fltr'='wmi|/assets/templates/new/img/others/logo_watermark.png|BR|100|0|0'

// функция разбивает строку вида "'param1'='value1',..." и возвращает массив
if (!function_exists('getconfigparam')) {
	// function getconfigparam($config_default_param, $type){
	function getconfigparam($config_default_param){
		foreach (explode(",",$config_default_param) as $parametr) {
			$param_arr=explode("=",$parametr);
				// $param[$type][$param_arr[0]]=$param_arr[1];
				$param[$param_arr[0]]=$param_arr[1];
		}
		return $param;
	}
}

// получаем конфигурацию по умолчанию
$config_default = array(
	"src"	=>	getconfigparam($config_default_src_param),
	"thumb" =>	getconfigparam($config_default_thumb_param)
);


if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Files0 - ".var_dump($file));
if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Files1 - ".print_r($files,true));
if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Files2 - ".print_r($modx->event->params['files'],true));
if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Files3 - ".print_r($modx->event->params['directory'],true));



// получаем media source
$ms = $modx->event->params['source'];
// расскоментировав след строку вы можете взглянуть на начинку источника
// if ($log) $modx->log(modX::LOG_LEVEL_INFO, print_r($ms->toArray(),true));


// if ( !$file || empty($ms) ) {
if ( empty($ms) ) {
	if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Грузим файл не стандартным путем, например из компонента GALLERY, параметры: ".print_r($files,true));
	return;
}


if ($auto_alias) {
		$currentdoc = $modx->newObject('modResource');
	}


foreach ($files as &$file) {

	// смотрим, что при загрузке не возникло ошибок
	if ($file['error'] != 0) {
		return $modx->error->failure($modx->lexicon('rionup_error_download'));
	}else{

	// параметры загружаемого файла
	// $file = $modx->event->params['files']['file'];
	$cur_dir = $modx->event->params['directory'];

	// $class_vars = get_class_vars(get_class($source));
	// $class_vars = get_class_methods(get_class($source));


	if (!empty($exclude_sources)){
		$exclude_sources = str_replace(' ', '', $exclude_sources);
		$exclude_sources=explode(",",$exclude_sources);
		foreach ($exclude_sources as $sources) {
			// if ($log) $modx->log(modX::LOG_LEVEL_INFO, is_numeric($sources));
			if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Sources list: $sources - ".$ms->name);
			if (is_numeric($sources)) {
				if ($ms->id==(int)$sources) return;
			}
			else {
				if ($ms->name==$sources) return;
			}
		}
	}


	// if($ms == null){
	// 2014-05-05 SETEST - этот способ устарел и работает уже не правильно, заменил на новое условие ниже
		// return $modx->error->failure("Ошибка, не удается получить данные об источнике файлов");
		// тут нужно проверить версию modx, если она младше 2.2.4 то
		// используем
		// foreach ($files as $file) {
			// $ext = @pathinfo($file['name'],PATHINFO_EXTENSION);
			// rename($cur_dir->getPath().$file['name'], $directory->getPath().UrlTranslit($file['name']));
		// }
		// тк в старых версиях вызов события загрузки шел один раз, проверить!
		// $cur_dir=$directory->getPath();

	// }
	//
	// if(empty($ms)){
	$msProperties = $ms->get('properties');
	if( empty($msProperties) ){
		$basePath = MODX_BASE_PATH;
		// $cur_dir .= $basePath;
		$cur_dir = "{$basePath}{$cur_dir}";
	}
	else {
		// настройки media source
		// $msProperties = $ms->get('properties');
		$basePath = $msProperties['basePath']['value'];
		$cur_dir  = $msProperties['basePath']['value'].$cur_dir;
		// на всякий случай проверяем наличие // и заменяем на /
		$cur_dir = str_replace('//', '/', $cur_dir);
		// if ($log) $modx->log(modX::LOG_LEVEL_INFO, "\$directory: ".print_r($directory,true));
		// if ($log) $modx->log(modX::LOG_LEVEL_INFO, "\$msProperties: ".var_dump($msProperties));
		if ($log) $modx->log(modX::LOG_LEVEL_INFO, "\$msProperties: ".print_r($msProperties,true));
		if ($log) $modx->log(modX::LOG_LEVEL_INFO, "\$cur_dir: $cur_dir");


	}


	if ($auto_alias){
				$file['name'] = $currentdoc->cleanAlias($file['name']);
				// удаляем файл и прерываем выполнение

				// $resultRemove=$fileAction->remove($cur_dir.$file['name']);
				// if ($log) $modx->log(modX::LOG_LEVEL_INFO, "REMOVE: ".$cur_dir . $file['name']);
				// if ($log) $modx->log(modX::LOG_LEVEL_INFO, "REMOVE result: ".$resultRemove);
				// return $modx->error->failure("oooops");
	}

	// проверяем на исключения директории
	// if ($exclude_dirs and $exclude_dirs=str_replace(' ', '', $exclude_dirs) and (in_array($cur_dir,explode(",",$exclude_dirs)))){
	if ($exclude_dirs and ($exclude_dirs=explode(",",$exclude_dirs))){
		if ($exclude_dirs_children) {

			foreach ($exclude_dirs as $path) {
				// if ($log) $modx->log(modX::LOG_LEVEL_INFO, "EXCLUDE DIRS CONDITIONS: $cur_dir == $path");
				// if ((strpos($path, $exclude_dirs_suffix) !== false) && (strpos($cur_dir, substr($path,0,-1*(strlen($exclude_dirs_suffix)))) !== false)) {
					// если содержит в строке &ExChild
					// if ($log) $modx->log(modX::LOG_LEVEL_INFO, "except {$exclude_dirs_suffix} in: {$path}, return;");
				// }
				// else {

					// if (strpos($cur_dir, str_replace($exclude_dirs_suffix, '', $path)) !== false) //return;
					if (strpos($cur_dir, $path) !== false) //return;
					{
						// return $modx->error->failure("children");
						if ($log) $modx->log(modX::LOG_LEVEL_INFO, "except children, return;");
						return;
					}
				// }
			}
		}
		else {
			foreach ($exclude_dirs as $path) {
				if ($log) $modx->log(modX::LOG_LEVEL_INFO, "EXCLUDE DIRS CONDITIONS: curdir ($cur_dir), exclude dir ($path)");
				if ((strpos($path, $exclude_dirs_suffix) !== false) && (strpos($cur_dir, substr($path,0,-1*(strlen($exclude_dirs_suffix)))) !== false)) {
					// если содержит в строке &ExChild
					if ($log) $modx->log(modX::LOG_LEVEL_INFO, "except {$exclude_dirs_suffix} in: {$path}, return;");
					return;
				}
				else {
					if ($cur_dir==str_replace($exclude_dirs_suffix, '', $path)) {
						if ($log) $modx->log(modX::LOG_LEVEL_INFO, "except dir, return;");
						return;
					}
				}
			// return $modx->error->failure("Записать в данную директорию нельзя она находится в исключениях плагина");
			// return $modx->error->failure("parent");
			}
		}
	}

	// $name = $file['name'];
	$name = urldecode($file['name']);
	// проверяем на исключения в имени файла
	if (!empty($exclude_part_of_name) and (strpos($name, $exclude_part_of_name) !== false)){
		if ($log) $modx->log(modX::LOG_LEVEL_INFO, "except part of name, return;");
		return;
	}

	$extensions = explode(',', $modx->getOption('upload_images'));
	if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Allowed extensions: ".implode(",", $extensions));
	// проверям, что наша категория задана в настройках плагина
	if ($config && array_key_exists($cur_dir, $config)) {
		$config = $config[$cur_dir];
	}
	else {
		$config=$config_default;
	}

	// if ($log) $modx->log(modX::LOG_LEVEL_INFO, print_r($config));

	// путь к файлу, имя файла, расширение
	// $filename = MODX_BASE_PATH.$cur_dir.$name;
	$filename = $cur_dir.$name;
	if ($log) $modx->log(modX::LOG_LEVEL_INFO, "SourceFilename: {$filename}");
	$def_fn = pathinfo($name, PATHINFO_FILENAME);
	$ext = pathinfo($name, PATHINFO_EXTENSION);
	// проверяем, что расширение файла задано в настройках MODX, как изображение
	if (in_array($ext, $extensions)) {

		// проверяем исключение расширение файла exclude_extensions
		if ($exclude_extensions and $exclude_extensions=str_replace(' ', '', $exclude_extensions) and ($exclude_extensions=explode(",",$exclude_extensions)) and (in_array($ext, $exclude_extensions))){
			// if (in_array($ext, $exclude_extensions)) {
			if ($log) $modx->log(modX::LOG_LEVEL_INFO, "except extension of file ({$ext}), return;");
			return;
		}

		// бежим по всем полям массива с конфигом
		foreach($config as $imgKey =>$imgConfig){
			$options = '';
			if($imgKey == 'src'){
				// для ключа src имя файла совпадает с исходным
				$imgName = $filename;
			}
			// elseif ($imgKey == 'thumb' and $create_thumbs){
			elseif ($imgKey == 'thumb'){
				if (!$create_thumbs) continue;				
				// формируем имя файла

				//$imgName = MODX_BASE_PATH.$cur_dir.$def_fn.'.'.$ext.'.'.$imgKey.'.'.$ext;
				if ($log) $modx->log(modX::LOG_LEVEL_INFO, "\$cur_dir: $cur_dir");
				if (!empty($thumbnail_dir) or !empty($default_thumb_path)) {
					// $thumbnail_dir = MODX_BASE_PATH.$cur_dir.$thumbnail_dir."/";
					$thumbnail_dir = $cur_dir.$thumbnail_dir."/";
					if (!empty($default_thumb_path)) {
						// если указана поапка по-умолчанию для превьюшек
						if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Default thumbnail path exist");
						if (substr($default_thumb_path, -1, 1)!="/") $default_thumb_path.="/";
						// $thumbnail_dir = MODX_BASE_PATH.$default_thumb_path;
						$thumbnail_dir = $basePath.$default_thumb_path;
					}
					if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Thumbnail full path: {$thumbnail_dir}");
					if(!is_dir($thumbnail_dir)) {
						if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Thumbnail dir not exist");
						if (!mkdir($thumbnail_dir,0755)){
							if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Thumbnail error:".$modx->lexicon('rionup_error_createdir'));
							return $modx->error->failure($modx->lexicon('rionup_error_createdir'));
						}
						else{
							if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Thumbnail dir created successfull");
						}
					}
					else {
						if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Thumbnail dir already exist");
					}
					// $filename = $thumbnail_dir.$name;
					// $imgName = $thumbnail_dir.$def_fn.'_'.$imgKey.'.'.$ext;
					$imgName = "{$thumbnail_dir}{$def_fn}{$thumb_key}_w{$imgConfig['w']}_h{$imgConfig['h']}.{$ext}";
				}
			}
			if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Part: {$imgKey}, imgName: {$imgName}");
			if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Config: ".print_r($imgConfig,1));

			// создаем объект phpThumb..
			$phpThumb = new phpThumb();
			// ..и задаем параметры
			// $thumbnail->setOptions($options); // хотел чере это сделать - не получилось
			$phpThumb->setSourceFilename($filename);

			if (empty($imgConfig['f'])){
				$imgConfig['f']=$ext; // без этого мы не увидим прозрачности в png и gif
			}
			if (!empty($imgConfig)){
				if (($ext=='png' or $ext=='gif') and !empty($imgConfig['fltr'])){
					// если расширение png или gif и применяется фильтр
					// подразумевается добавление watermark
					// и чтобы наш watermark не распологался на цветном фоне
					// мы его удаляем
					// if ($log) $modx->log(modX::LOG_LEVEL_INFO, "Destroy background for watermark");
					// unset($imgConfig['bg']);
				}

				foreach ($imgConfig as $k => $v) {
					// if ($log) $modx->log(modX::LOG_LEVEL_INFO,"$k - $v");
					$phpThumb->setParameter($k, $v);
				}
			}

			// генерируем файл
			if ($log) $modx->log(modX::LOG_LEVEL_INFO, "GenerateThumbnail...");
			if ($phpThumb->GenerateThumbnail()) {
				if ($log) $modx->log(modX::LOG_LEVEL_INFO, "RenderToFile: $imgName");
				if ($phpThumb->RenderToFile($imgName)) {
					// устанавливаем права на файл, это опционально, зависит от сервера
					chmod($imgName, 0666);
				}
			}
		}
	}
	else {
		if ($log) $modx->log(modX::LOG_LEVEL_INFO, "extension of file ({$ext}) is forbidden for download in system parameters 'upload_images'. Allow only: ".implode(",", $extensions));
		return $modx->error->failure($modx->lexicon('rionup_error_extension', array('ext' => $ext)));
	}
}
}
return;
