<?php

$viewPath = 'modules/LPInstaller/views/LPI.php';
// URL a la que quieres redirigir si el archivo de vista existe
$redirectUrl = 'index.php?module=LPInstaller&view=LPI';
// Verifica si el archivo de vista existe
if (file_exists($viewPath)) {
    // Si existe, redirige a la URL deseada
    header("Location: $redirectUrl");
    exit;
}


require_once('include/utils/utils.php');
require_once('include/database/PearDatabase.php');
require_once('includes/Loader.php');
require_once('modules/ServiceContracts/ServiceContracts.php');
require_once 'includes/runtime/LanguageHandler.php';

ini_set('display_errors', 'on');
error_reporting(E_ERROR);

global $adb, $log, $default_timezone;
global $site_URL, $application_unique_key;
global $default_language;
global $current_language;
global $default_theme;
global $current_user;

if (!$current_user) {
    include_once 'includes/main/WebUI.php';
    $webUI = new Vtiger_WebUI();
    Vtiger_Session::init();
    $current_user = $webUI->getLogin();
}

vimport('includes.http.Request');
vimport('includes.runtime.Globals');
vimport('includes.runtime.BaseModel');
vimport('includes.runtime.Controller');

include_once('modules/com_vtiger_workflow/VTEntityMethodManager.inc');

$vtlib = $_POST['vtlib'];
if ($vtlib && !empty($vtlib)) {
    if (!$current_user || $current_user->is_admin !== "on" || $current_user->is_admin === false) {
        echo "NO SOS ADMIN, TOMATELAS‼🤐🚫🚫";
        exit();
    }
    $Vtiger_Utils_Log = true;
    // ini_set('display_errors','on'); error_reporting(E_ALL); 
    abstract class BaseVtliv
    {
        protected $modulename;
        protected $operations = array();
        protected $moduleInstance;
        protected $parent = "Marketing";
        abstract function process();
        function createModule()
        {
            $moduleInstance = Vtiger_Module::getInstance($this->modulename);
            if (!$moduleInstance) {
                $moduleInstance = new Vtiger_Module();
                $moduleInstance->name = $this->modulename;
                $moduleInstance->parent = $this->parent;
                $moduleInstance->save();
                static::addModuleToApp($moduleInstance);
                static::crearEstructuraArchivosModulo($this->modulename);
                $moduleInstance->initTables();
            } else {
                echo "El modulo ya existe\n";
            }
            $this->moduleInstance = $moduleInstance;
        }
        function defaultModuleConfig()
        {
            /** Set sharing access of this module */
            $this->moduleInstance->setDefaultSharing('Public');
            /** Enable and Disable available tools */
            $this->moduleInstance->enableTools(array('Import', 'Export'));
            $this->moduleInstance->disableTools('Merge');
            $this->moduleInstance->initWebservice();
        }
        function createBlock($label)
        {
            $blockInstance = Vtiger_Block::getInstance($label, $this->moduleInstance);
            if (!$blockInstance) {
                $blockInstance = new Vtiger_Block();
                $blockInstance->label = $label;
                $this->moduleInstance->addBlock($blockInstance);
            } else {
                echo "El bloque $label ya existe\n";
            }
            return $blockInstance;
        }
        function createField($data, $blockInstance)
        {
            $fieldInstance = Vtiger_Field::getInstance($data['name'], $this->moduleInstance);
            if (!$fieldInstance) {
                $fieldInstance = new Vtiger_Field();
                $fieldInstance->name = $data["name"];

                $opcionales = array("typeofdata", "uitype", "columntype", "helpinfo", "summaryfield", "masseditable", "presence", "maximumlength", "sequence", "quickcreate", "quicksequence", "info_type", "isunique", "headerfield", "defaultvalue");
                foreach ($opcionales as $fiendAttr)
                    if (isset($data[$fiendAttr]) && !empty($data[$fiendAttr])) {
                        $fieldInstance->$fiendAttr = $data[$fiendAttr];
                    }


                if (isset($data["displaytype"]) && !empty($data["displaytype"])) {
                    $fieldInstance->displaytype = $data["displaytype"];
                } else {
                    $fieldInstance->displaytype = 1;
                }
                if (isset($data["label"]) && !empty($data["label"])) {
                    $fieldInstance->label = $data["label"];
                } else {
                    $fieldInstance->label = $fieldInstance->name;
                }
                if (isset($data["column"]) && !empty($data["column"])) {
                    $fieldInstance->column = $data["column"];
                } else {
                    $fieldInstance->column = $fieldInstance->name;
                }
                if (isset($data["table"]) && !empty($data["table"])) {
                    $fieldInstance->table = $data["table"];
                } else {
                    $fieldInstance->table = $this->moduleInstance->basetable;
                }

                $blockInstance->addField($fieldInstance);
                if (isset($data["setRelatedModules"]) && !empty($data["setRelatedModules"]) && is_array($data["setRelatedModules"])) {
                    $fieldInstance->setRelatedModules($data["setRelatedModules"]);
                }
                if (isset($data["setPicklistValues"]) && !empty($data["setPicklistValues"]) && is_array($data["setPicklistValues"])) {
                    $fieldInstance->setPicklistValues($data["setPicklistValues"]);
                }
            }
            return $fieldInstance;
        }
        function agregarRelacion($relatedToModuleName, $label, $fn, $actions = array(), $fieldId = null)
        {
            if (!$this->moduleInstance)
                $this->createModule();
            $fn = $fn;
            $relationLabel = $label;
            $relatedToModule = Vtiger_Module::getInstance($relatedToModuleName);
            $this->moduleInstance->unsetRelatedList($relatedToModule, $relationLabel, $fn);
            $this->moduleInstance->setRelatedList($relatedToModule, $relationLabel, $actions, $fn, $fieldId);
        }
        function createFilter($fields, $name = "All", $deleteOthers = true)
        {
            if ($deleteOthers)
                Vtiger_Filter::deleteForModule($this->moduleInstance);
            $filter1 = new Vtiger_Filter();
            $filter1->name = $name;
            $filter1->isdefault = true;
            $this->moduleInstance->addFilter($filter1);
            // invertir orden
            foreach (array_reverse($fields) as $field)
                $filter1->addField($field);

        }
        function crearEstructuraArchivosModulo()
        {
            $targetpath = 'modules/' . $this->modulename;
            $fieldid = strtolower($this->modulename);
            if (!is_file($targetpath)) {
                mkdir($targetpath, 0777);
                mkdir($targetpath . '/language', 0777);
                $templatepath = 'vtlib/ModuleDir/6.0.0';
                $moduleFileContents = file_get_contents($templatepath . '/ModuleName.php');
                $replacevars = array(
                    'ModuleName' => $this->modulename,
                    '<modulename>' => strtolower($this->modulename),
                    '<entityfieldlabel>' => $fieldid,
                    '<entitycolumn>' => $fieldid,
                    '<entityfieldname>' => $fieldid,
                );
                foreach ($replacevars as $key => $value)
                    $moduleFileContents = str_replace($key, $value, $moduleFileContents);
                file_put_contents($targetpath . '/' . $this->modulename . '.php', $moduleFileContents);
            }
        }

        function createWSOperations()
        {
            global $adb;
            if (!$this->operations || !is_array($this->operations))
                return false;
            foreach ($this->operations as $op) {
                $NA = $op["name"];
                $HF = $op["handlerFilePath"];
                $HM = $op["handlerMethodName"];
                $RT = $op["requestType"];
                $PL = $op["preLogin"];
                $PA = $op["params"];
                $sql = 'SELECT operationid from vtiger_ws_operation WHERE name=? AND handler_path=? AND handler_method=?';
                $exist = $adb->pquery($sql, array($NA, $HF, $HM));
                if ($adb->num_rows($exist) > 0) {
                    $old_id = $adb->query_result($exist, 0, "operationid");
                    vtws_deleteWebServiceOperation($old_id);
                    echo "Se elimina la operacion con id $old_id \n";
                }
                $operationId = vtws_addWebserviceOperation($NA, $HF, $HM, $RT, $PL);
                echo "Operacion $NA creada como con id $operationId \n";
                if ($PA && is_array($PA)) {
                    foreach ($PA as $i => $param) {
                        list($param_name, $param_type) = $param;
                        vtws_addWebserviceOperationParam($operationId, $param_name, $param_type, $i + 1);
                        echo "$NA nuevo parametro => $param_name, $param_type \n";
                    }
                }
            }
            return true;
        }
        static function addModuleToApp($module)
        {
            $db = PearDatabase::getInstance();
            $parent = strtoupper($module->parent);
            $menu = Vtiger_Menu::getInstance($parent);
            $menu->addModule($module);
            $result = $db->pquery('SELECT * FROM vtiger_app2tab WHERE tabid = ? AND appname = ?', array($module->getId(), $parent));
            if ($db->num_rows($result) == 0) {
                $resultSec = $db->pquery('SELECT MAX(sequence) AS maxsequence FROM vtiger_app2tab WHERE appname=?', array($parent));
                $sequence = 0;
                if ($db->num_rows($resultSec) > 0)
                    $sequence = intval($db->query_result($resultSec, 0, 'maxsequence')) + 1;
                $db->pquery('INSERT INTO vtiger_app2tab(tabid,appname,sequence) VALUES(?,?,?)', array($module->getId(), $parent, $sequence));
            }
        }
    }
    echo "<hr>\n";
    if (file_exists("modules/$vtlib")) {
        echo "--- <b>Importando Instalador $vtlib </b> ---\n";
        include_once "modules/$vtlib";
        list($order, $module, $action) = explode(".", str_replace([".php", "vtlib."], "", $vtlib));
        $classNAme = "${module}_${action}";
        if (class_exists($classNAme)) {
            $_ = new $classNAme();
            $wea = $_->process();
            if ($wea)
                echo "<br>😁 Ejecucion <b>${module} ${action}</b> finalizada correctamente ✅✅✅";
            else
                echo "<br>🤯 Ejecucion <b>${module} ${action}</b> finalizada con error ⚠️⚠️⚠️";
        } else {
            echo "<br>😁 Ejecucion <b>${module} ${action}</b> finalizada correctamente";
        }
        return;
    } else {
        echo "<br>🧐 NO se encuentra el archivo $vtlib ⚠️⚠️⚠️";
        return;
    }
}

$gitlab_token = "glpat-kBrT-dcZzb7cv6uDHYrv";

function downloadZipFile($url, $destination)
{
    global $gitlab_token;
    
    $options = [
        'http' => [
            'header' => "PRIVATE-TOKEN: $gitlab_token"
        ]
    ];
    
    $context = stream_context_create($options);
    
    $file = file_get_contents($url, false, $context);
    
    echo "Descargando $url<br />";
    usleep(10);
    ob_flush();
    flush();
    if ($file !== false) {
        file_put_contents($destination, $file);
        echo "Repositorio descargado con éxito.<br/>";
        usleep(10);
        ob_flush();
        flush();
    } else {
        echo "Error al descargar el repositorio.<br/>";
        usleep(10);
        ob_flush();
        flush();
        return false;
    }
    return true;
}
function extractZipFile($zipFilePath, $extractPath)
{
    $zip = new ZipArchive;
    if ($zip->open($zipFilePath) === TRUE) {
        
        // Obtener el nombre del directorio raíz del archivo ZIP (por ejemplo, "repositorio-master/")
        $rootDir = $zip->getNameIndex(0);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Ajustar la ruta de destino para omitir el directorio raíz
            $fileDest = str_replace($rootDir, '', $filename);

            // Extraer el archivo a la nueva ruta de destino
            $zip->extractTo($extractPath, $filename);
            if ($fileDest !== $filename) {
                rename($extractPath . '/' . $filename, $extractPath . '/' . $fileDest);
            }
        }

        $zip->close();
        return true;
    } else {
        return false;
    }
}
function backupAndCopyFiles($source, $destination, $backupPath, $modulename)
{
    if (file_exists($destination)) {
        // Comparar el contenido de los archivos
        $sourceContent = file_get_contents($source);
        $destinationContent = file_get_contents($destination);
        if ($sourceContent !== $destinationContent) {
            // Crear la estructura de carpetas para la copia de seguridad si no existe
            $destinationDir = dirname($destination);
            $backupDir = $backupPath . $destinationDir;
            if (!file_exists($backupDir)) {
                if (!mkdir($backupDir, 0777, true)) {
                    echo "<b class='text-red-800'>Error al crear el directorio de respaldo: " . $backupDir . "</b><br />";
                    $error = error_get_last();
                    echo "<em class='text-red-800'> " .$error['message']. "</em> <br />";
                    usleep(10);
                    ob_flush();
                    flush(); 
                    return;
                }
            }
            // Realizar copia de seguridad
            if (copy($destination, $backupPath . $destination)) {
                chmod( $backupPath . $destination, 0777);
                echo "Archivo de respaldo creado: " . $backupPath . $destination . "<br>";
                usleep(10);
                ob_flush();
                flush(); 
            } else {
                echo "<b class='text-red-800'>Error al crear el archivo de respaldo: " . $backupPath . $destination . "</b><br>";
                usleep(10);
                ob_flush();
                flush(); 
            }
        } else {
            // echo "Esta Igual: <em>" . $destination . "</em><br>";
            return;
        }
    }
    // Copiar el archivo
    $destinationFolder = dirname($destination);
    if (!file_exists($destinationFolder)) {
        if (!mkdir($destinationFolder, 0777, true)) {
            echo "<b class='text-red-800'>Error al crear el directorio de destino: " . $destinationFolder . "</b><br>";
            return;
        }
    }
    if (copy($source, $destination)) {
        chmod( $backupPath . $destination, 0777);
        echo "Archivo copiado: <em class='text-red-400'>" . $destination . "</em><br>";
        usleep(10);
        ob_flush();
        flush(); 
        // if (file_exists($backupPath . $destination)) {
        //     $newContentWithComments = compareFilesWithDiffLib($backupPath . $destination, $destination, $modulename);
        //     file_put_contents($backupPath . $destination . ".merge", $newContentWithComments);
        // }
    } else {
        echo "Error al copiar el archivo: " . $destination . "<br>";
        usleep(10);
        ob_flush();
        flush(); 
    }
}
function deleteTempFiles($zipFilePath, $tempFolder)
{
    unlink($zipFilePath);
    rrmdir($tempFolder);
}

function rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                    rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                else
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }
}
function getIgnoredPaths($sourcePath)
{
    $ignoreFilePath = $sourcePath . '/.lpignore';
    $ignoredPaths = [$ignoreFilePath];

    if (file_exists($ignoreFilePath)) {
        $lines = file($ignoreFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignora comentarios
            if (strpos($line, '#') === 0) {
                continue;
            }
            $ignoredPaths[] = "$sourcePath/$line";
        }
    }

    return $ignoredPaths;
}

function shouldBeIgnored($path, $ignoredPaths)
{
    foreach ($ignoredPaths as $ignoredPath) {
        if (fnmatch($ignoredPath, $path)) {
            return true;
        }
    }
    return false;
}

function processFolder($sourcePath, $destinationPath, $backupPath, $modulename)
{
    $ignoredPaths = getIgnoredPaths($sourcePath);
    $dir = opendir($sourcePath);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $sourcePath . '/' . $file;
        $destinationFile = ($destinationPath === '' ? '' : $destinationPath . '/') . $file;

        if (shouldBeIgnored($sourceFile, $ignoredPaths)) {
            echo " <em class='text-amber-500'>Ignorado: <b>$file</b></em><br>";
            continue;
        }

        if (is_dir($sourceFile)) {
            processFolder($sourceFile, $destinationFile, $backupPath, $modulename);
            usleep(10);
            ob_flush();
            flush(); 
        } else {
            backupAndCopyFiles($sourceFile, $destinationFile, $backupPath, $modulename);
            usleep(10);
            ob_flush();
            flush(); 
        }
    }
    closedir($dir);
}

if (isset($_POST['paths']) && is_array($_POST['paths'])) {
    $paths = $_POST['paths'];

    $zip = new ZipArchive();
    $filename = "test/tempmodule.zip";

    // Si el archivo ZIP ya existe, elimínalo
    if (file_exists($filename)) {
        unlink($filename);
    }

    if ($zip->open($filename, ZipArchive::CREATE) !== TRUE) {
        exit("No se puede abrir el archivo <$filename>\n");
    }

    // Función recursiva para agregar archivos y directorios al ZIP
    function addFilesToZip($zip, $path, $zipPath = '') {
        echo "Comprimiendo $path <br />";
        if (is_file($path)) {
            $zip->addFile($path, $zipPath);
        } elseif (is_dir($path)) {
            if ($zipPath) { // Si hay un path para el ZIP, crea un directorio vacío
                $zip->addEmptyDir($zipPath);
            }
            $dir = new DirectoryIterator($path);
            foreach ($dir as $fileinfo) {
                if (!$fileinfo->isDot()) {
                    $newPath = $path . '/' . $fileinfo->getFilename();
                    $newZipPath = $zipPath ? $zipPath . '/' . $fileinfo->getFilename() : $fileinfo->getFilename();
                    addFilesToZip($zip, $newPath, $newZipPath);
                }
            }
        }
    }
    
    foreach ($paths as $path) {
        ob_flush();
        flush(); 
        addFilesToZip($zip, $path, $path);
        usleep(10);
        ob_flush();
        flush(); 
    }
    
    $zip->close();
    echo "<p class='text-green-800'>Listo!</p>";
    return;
} 


if (isset($_GET['project_id']) && isset($_GET['readme_url'])) {
    $urlReadMe = $_GET['readme_url'];
    $projectId = $_GET['project_id'];
    // Extraer el nombre del archivo y la rama del $urlReadMe
    preg_match("#/-/blob/([^/]+)/(.+)$#", $urlReadMe, $matches);
    $branchName = $matches[1];
    $filePath = urlencode($matches[2]);
    
    // Construir la URL de la API
    $apiUrl = "https://git.luderepro.com/api/v4/projects/$projectId/repository/files/$filePath/raw?ref=$branchName";
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "PRIVATE-TOKEN: $gitlab_token"
    ]);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        echo $response; // Esto mostrará el contenido del archivo.
    }
    
    curl_close($ch);
    
    return;    
}

if (isset($_GET['project_id'])) {
    $projectId = $_GET['project_id'];
    $projectName = $_GET['project_name'];
    $path = $_GET['path'];
    $pathWithNamespace = $_GET['path_with_namespace'];

    $branchesUrl = "https://git.luderepro.com/api/v4/projects/$projectId/repository/branches?access_token=$gitlab_token";
    $branchesData = file_get_contents($branchesUrl);
    $branches = json_decode($branchesData, true);

    foreach ($branches as $branch) {
        // $commitUrl = "https://git.luderepro.com/api/v4/projects/$projectId/repository/commits/{$branch['name']}?access_token=$gitlab_token";
        // $commitData = file_get_contents($commitUrl);
        $commit = $branch["commit"]; //json_decode($commitData, true);
        ?>
        <div class="bg-gray-200 rounded-sm justify-between flex">
            <div class="p-2">
                <strong><?= $branch['name'] ?></strong>
                <p class="text-sm text-gray-600"><?= $commit['message'] ?></p>
                <p class="text-xs text-gray-500">Por <b><?= $commit['author_name'] ?></b> el <?= date("d/m/Y H:i", strtotime($commit['committed_date'])) ?></p>
            </div>
            <button class="bg-sky-700 text-white text-xs rounded-r px-2 py-1 float-right module-tile"
            data-modulename="<?=$projectName ?>"
            data-modulezip="https://git.luderepro.com/<?=$pathWithNamespace ?>/-/archive/<?=$branch['name'] ?>/<?=$path ?>.zip"
            >Instalar</button>
        </div>
        <?php
    }
    return;
}

$modulename = $_POST['modulename'];
$modulezip = $_POST['modulezip'];
if ($modulezip && !empty($modulezip) && $modulename && !empty($modulename)) {
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
        apache_setenv('dont-vary', '1');
    }
    ob_end_clean();    
    $tempZipPath = "test/${modulename}.zip";
    $tempFolderPath = "test/${modulename}/";
    $backupFolderPath = "test/lpmodulesbackups/${modulename}/" . date('Y-m-d_H-i-s') . '/';
    if(!downloadZipFile($modulezip, $tempZipPath)) return;
    usleep(10);
    ob_flush();
    flush(); 
    echo "Extrayendo archivo <br />";
    usleep(10);
    ob_flush();
    flush(); 
    if (extractZipFile($tempZipPath, $tempFolderPath)) {
        usleep(10);
        ob_flush();
        flush(); 
        echo "Copiando archivos <br />";
        ob_flush();
        flush(); 
        // Procesa todos los archivos extraídos y realiza copias de seguridad y copias de archivos necesarios
        processFolder($tempFolderPath, '', $backupFolderPath, $modulename);
        usleep(10);
        ob_flush();
        flush(); 
        // Eliminar archivos temporales
        deleteTempFiles($tempZipPath, $tempFolderPath);
        echo "$modulename Instalado correctamente!";
        exit();
    } else {
        echo "Error al extraer el archivo ZIP.";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>

    <!-- Marked para la conversión de Markdown a HTML -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <!-- highlight.js para el resaltado de sintaxis -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.2.0/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.2.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.js" integrity="sha512-zlWWyZq71UMApAjih4WkaRpikgY9Bz1oXIW5G0fED4vk14JjGlQ1UmkGM392jEULP8jbNMiwLWdM8Z87Hu88Fw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-toast-plugin/1.3.2/jquery.toast.min.css" integrity="sha512-wJgJNTBBkLit7ymC6vvzM1EcSWeM9mmOu+1USHaRBbHkm6W9EgM0HY27+UtUaprntaYQJF75rc8gjxllKs5OIQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <style>
        .results pre {
            border: 1px solid;
            border-radius: 5px;
            padding: 5px 10px;
            box-shadow: inset 0px 0px 418px 70px #ddd1;
            overflow: overlay;
        }
        .results > * {
            padding-top: 5px;
        }
        .results ol,
        .results ol li,
        .results ul,
        .results ul li{
            list-style: inside;
        }
        .results > h1 {
            font-size: 2em;
            margin: .5em 0;
        }
        .results > h2 {
            font-size: 1.5em;
            margin: .4em 0;
        }
        .results > h3 {
            font-size: 1.2em;
            margin: .3em 0;
        }
        .results > h4 {
            font-size: 1em;
            margin: .2em 0;
        }
    </style>
</head>

<body class="font-sans text-sm text-gray-900 bg-white bg-opacity-80 bg-gradient-to-br grid-cols-5 grid-rows-5">

    <?php
    $tab = $_GET["tab"];
    if (!$current_user || $current_user->is_admin!= "on") {
        echo "<div class='text-center text-lg text-red-600 text-shadow uppercase'> <br> ⚠️ <br> <br>  Primero accede con un usuario de administrador <br><br><a href='index.php'>Acceder</a></div>";
        exit();
    }
    ?>

    <div class="text-center text-sky-700 font-bold text-shadow-md text-lg uppercase h-[5vh] w-[100%]">
        INSTALACION DE CARACTERISTICAS
        <?php $companyDetails = Vtiger_CompanyDetails_Model::getInstanceById(); echo $companyDetails->get('organizationname'); ?>
    </div>


    <div class="px-5 mx-auto flex h-[95vh] w-screen">
        <!-- Panel de vtlibs y módulos -->
        <div class="relative w-2/5 overflow-y-auto rounded">
            <!-- Pestañas -->
            <div id="content-tabs" class="sticky w-[100%] bg-white flex z-10 top-0 pb-2 justify-between">
                <div class="flex p-0 m-0">
                    <a class="px-4 py-2 <?php if (!$tab || $tab === "vtlibs"): ?> bg-sky-700 text-white <?php else: ?> bg-gray-300 <?php endif; ?> rounded-tl" id="tab-vtlibs" href="LPI.php?tab=vtlibs">VTLibs</a>
                    <a class="px-4 py-2 <?php if ($tab === "modulos"): ?> bg-sky-700 text-white <?php else: ?> bg-gray-300 <?php endif; ?>" id="tab-modules" href="LPI.php?tab=modulos">Módulos</a>
                    <a class="px-4 py-2 <?php if ($tab === "extraer"): ?> bg-sky-700 text-white <?php else: ?> bg-gray-300 <?php endif; ?> rounded-tr" id="tab-extraer" href="LPI.php?tab=extraer">Extraer</a>
                </div>
                <div>
                    <button class="px-4 py-2 bg-gray-300 rounded-l borrador">🧹</button>
                </div>
            </div>
            <!-- Contenido de VTLibs -->
            <?php if (!$tab || $tab === "vtlibs"): ?>
            <div id="content-vtlibs" class="my-2">
                    <div class="space-y-4">
                        <?php
                        $first_ele = "";
                        foreach (scandir("modules") as $modules) {
                            $mostrarmodule = true;
                            $vtlibs = scandir("modules/$modules/vtlibs");
                            if (is_array($vtlibs)) {
                                foreach (preg_grep('~^vtlib.*\.php$~', $vtlibs) as $i_f) {
                                    $action = str_replace(["vtlib.", ".php"], "", $i_f);
                                    if ($mostrarmodule)
                                        echo "<ol class='mt-2 border rounded-lg hover:bg-sky-100 m-2 '><li>
                                            <h2 class='text-gray-700 text-center mt-2 font-bold'>$modules</h2></li>";
                                    $mostrarmodule = false;
                                    ?>
                                    <li class="my-2 transition-all duration-200 hover:mx-1">
                                        <a data-type="instalink" href="#" data-vtlib="<?= $modules ?>/vtlibs/<?= $i_f ?>" 
                                        class="block px-2 py-2 bg-gray-100 hover:bg-sky-700  rounded-lg shadow transition-transform duration-200 transform scale-95 hover:scale-100 hover:shadow-lg active:bg-blue-500  text-gray-900 hover:text-white  active:text-white">
                                            <span class="inline-block text-center rounded-full leading-6">
                                                <?= $action ?>
                                            </span>
                                        </a>
                                    </li>
                                    <?php
                                }
                            }
                            if (!$mostrarmodule) { ?>
                                </ol>
                            <?php }
                        } ?>
                        </ol>
                    </div>
            </div>
            <?php elseif($tab === "modulos"): ?>
            <!-- Contenido de Módulos -->
            <div id="content-modules" class="my-2 mr-2 grid grid-cols-1 gap-1 overflow-x-hidden">
                <?php
                $jsonUrl = "https://git.luderepro.com/api/v4/groups/modulos/projects?access_token=$gitlab_token";
                $jsonData = file_get_contents($jsonUrl);
                $jsonmodules = json_decode($jsonData, true);
                ?>
                <?php foreach ($jsonmodules as $jsonmodule): ?>
                    <div class="relative overflow-hidden transition-all
                    duration-300 border border-gray-400 rounded p-2 divider
                    hover:rounded-lg hover:shadow-lg">
                        <div class="flex">
                            <img 
                            data-readme_url="<?= $jsonmodule['readme_url']?>"
                            data-project-id="<?= $jsonmodule['id'] ?>"
                            src="<?= $jsonmodule['avatar_url'] ?: "https://git.luderepro.com/".$jsonmodule['namespace']['avatar_url'] ?>" alt="<?= $jsonmodule['name'] ?>" class="h-12 w-12 rounded-full object-cover cursor-pointer module-avatar">
                            <div class="pl-2">
                                <h3 class="text-md font-bold"><?= $jsonmodule['name'] ?></h3>
                                <span class="text-sm text-gray-700"><?= $jsonmodule['description'] ?></span>
                                <p class="text-xs text-gray-500">Editado el <?= date("d/m/Y h:i", strtotime($jsonmodule['last_activity_at'])) ?></p>
                            </div>
                        </div>
                        <div class="border-t my-2"></div>
                        <div class="branches-container space-y-2 hidden"></div>
                        <button class="load-branches-btn w-full text-center" 
                        data-project-id="<?= $jsonmodule['id'] ?>"
                        data-project_name="<?= $jsonmodule['name'] ?>"
                        data-path="<?= $jsonmodule['path'] ?>"
                        data-path_with_namespace="<?= $jsonmodule['path_with_namespace'] ?>"
                        >Mostrar ramas</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php elseif($tab === "extraer"): ?>
                <?php
                    $selectedDate = $_GET['fecha'] ?? date('Y-m-d');
                    $timestamp = strtotime($selectedDate);
                    $directory = getcwd(); // Obtiene el directorio actual

                    $pathsToIgnore = [
                        'logs/',
                        'test/',
                        'cache/',
                        'user_privileges/',
                        'config.inc.php',
                        'readme.md',
                        'Readme.md',
                        'README.md',
                        '.vscode',
                        '.gitignore',
                        '.git',
                    ];

                    $displayedPaths = [];
                    $allPaths = [];

                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
                    foreach ($files as $file) {
                        $relativePath = str_replace($directory . '/', '', $file->getPathname());
                        $allPaths[] = $relativePath;

                        // Verificar si el path está en la lista de ignorados
                        $ignore = false;
                        foreach ($pathsToIgnore as $ignorePath) {
                            if (strpos($relativePath, $ignorePath) === 0) {
                                $ignore = true;
                                break;
                            }
                        }

                        if (!$ignore && $file->isFile() && $file->getMTime() >= $timestamp) {
                            $displayedPaths[] = $relativePath;
                        }
                    }

                    $autocompletePaths = array_diff($allPaths, $displayedPaths);
                ?>
                <div id="content-extraer" class="my-2 mr-2 ">
                    <!-- Controles para filtrar por fecha -->
                    <div class="mb-4 justify-between items-center space-y-1">
                        <div class="flex justify-between">
                            <label for="filterDate" class="w-[75%] my-auto">
                                Fecha desde:
                                <input type="date" id="filterDate" value="<?php echo $_GET['fecha'] ?? date('Y-m-d'); ?>">
                            </label>
                            <button id="filterBtn" class="bg-sky-700 text-white p-2 ml-1 rounded w-[25%]">Filtrar</button>
                        </div>
                        <div class="flex justify-between">
                            <input type="text" id="newPathInput" placeholder="Ingresar nuevo path" 
                                class="border rounded-l p-2 w-[75%] focus:outline-none">
                                
                            <button id="addPathBtn" class="bg-sky-700 text-white p-2 rounded-r w-[25%]">Agregar Path</button>
                        </div>
                        <div class="flex justify-between">
                        <button id="extractBtn" class="bg-sky-700 text-white p-2 rounded w-full">Generar ZIP</button>
                        </div>
                    </div>

                    <!-- Resultados basados en la fecha de filtrado -->
                    <div id="gitStatusResults">
                        <?php foreach ($displayedPaths as $path): ?>
                            <label class="block pathItem">
                                <input type='checkbox' checked data-path='<?php echo $path; ?>'> 
                                <?php echo $path; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                </div>
                <script>
                    $(document).ready(function() {
                        $('#filterBtn').click(function() {
                            var selectedDate = $('#filterDate').val();
                            window.location.href = 'LPI.php?tab=extraer&fecha=' + selectedDate;
                        });
                        $('#addPathBtn').click(function() {
                            var newPathInput = $('#newPathInput').val();
                            if (!newPathInput?.length) return;
                            $("#gitStatusResults").prepend(`<label class='block pathItem'>
                                <input type='checkbox' checked data-path='${newPathInput}'> ${newPathInput}
                            </label>
                            `);
                            $("#pathList option[value='" + newPath + "']").remove();
                            $('#newPathInput').val("");
                        });
                        
                        $('#extractBtn').click(function() {
                            let paths = [];
                            $('.pathItem input:checked').each(function() {
                                paths.push($(this).data("path"));
                            });
                            $.ajax({
                                url: "LPI.php",
                                type: 'POST',
                                dataType: 'text',
                                data: {paths},
                                xhrFields: {
                                    onprogress: function(e) {
                                        var response = e.currentTarget.responseText;
                                        console.log(e.currentTarget.responseText);
                                        // $.toast(e.responseText, {enableHtml: true})
                                        let results = e.currentTarget.responseText.split('\n');
                                        for (let i = 0; i < results.length; i++) {
                                            $(".results").append("<div>" + results[i] + "<div>")
                                        }
                                        $(".results").parent().animate({ scrollTop: $(".results")[0].scrollHeight }, 500);
                                    }
                                },
                                // hides the loader after completion of request, whether successfull or failor.             
                                complete: function (data) {
                                    console.log(data)
                                    if (data?.statusText === "OK") {
                                        $(".results").append("<div>Descargando<div>")
                                        // Forzar la descarga del archivo ZIP sin recargar la página
                                        var link = document.createElement('a');
                                        link.href = 'test/tempmodule.zip';
                                        link.download = 'LPIModule.zip';
                                        document.body.appendChild(link);
                                        link.click();
                                        document.body.removeChild(link);
                                    } else {
                                        $(".results").append("Hubo un error al crear el archivo ZIP. <br/>");
                                    }
                                }
                            });
                        });
                    });
                </script>
              
            <?php endif; ?>
        </div>

        <!-- Panel de resultados -->
        <div class="w-3/5 text-gray-200 overflow-auto bg-gray-900 rounded mb-2 p-3">
            <div class="results overflow-y-auto">
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('.module-avatar').on('click', function() {
                var $this = $(this);
                var readme_url = $this.data('readme_url');
                var project_id = $this.data('project-id');
                $.ajax({
                    url: "LPI.php",
                    method: "GET",
                    data: {
                        project_id,
                        readme_url
                    },
                    success: function(data) {
                        marked.setOptions({
                            highlight: function (code, language) {
                            const validLanguage = hljs.getLanguage(language)
                                ? language
                                : "plaintext";
                            return hljs.highlight(validLanguage, code).value;
                            },
                        });

                        // Convierte el contenido del archivo .md a HTML
                        const html = marked.marked(data);
                        $(".results").append(html)                       
                    }
                })
            })
            $('.load-branches-btn').on('click', function() {
                var $btn = $(this);
                var projectId = $btn.data('project-id');
                var project_name = $btn.data('project_name');
                var path = $btn.data('path');
                var path_with_namespace = $btn.data('path_with_namespace');
                var $container = $btn.prev('.branches-container');
                $(".results").append(`<h1>Buscando ramas en <b>${project_name}</b>.<h1>`);

                // Si las ramas ya están cargadas, simplemente muestra u oculta el contenedor.
                if ($container.children().length) {
                    $container.toggleClass('hidden');
                    return;
                }

                // Carga las ramas de manera asíncrona.
                $.ajax({
                    url: "LPI.php",
                    method: "GET",
                    data: {
                        project_id: projectId,
                        project_name,
                        path,
                        path_with_namespace,
                        access_token: "<?= $gitlab_token ?>"
                    },
                    success: function(data) {
                        $container.html(data).removeClass('hidden');
                        $btn.toggleClass('hidden');
                        
                        $container.find(".module-tile").click(e => {
                            e.preventDefault();
                            let modulename = $(e.currentTarget).data("modulename");
                            let modulezip = $(e.currentTarget).data("modulezip");
                            console.log(e)
                            $(".results").append("<div>Instalar " + modulename + "</div>")
                            $(".results").parent().animate({ scrollTop: $(".results")[0].scrollHeight }, 500);
                            $.ajax({
                                url: "LPI.php",
                                type: 'POST',
                                dataType: 'text',
                                data: { modulename, modulezip },
                                xhrFields: {
                                    onprogress: function(e) {
                                        var response = e.currentTarget.responseText;
                                        console.log(e.currentTarget.responseText);
                                        // $.toast(e.responseText, {enableHtml: true})
                                        let results = e.currentTarget.responseText.split('\n');
                                        for (let i = 0; i < results.length; i++) {
                                            $(".results").append("<div>" + results[i] + "<div>")
                                        }
                                        $(".results").parent().animate({ scrollTop: $(".results")[0].scrollHeight }, 500);
                                    }
                                },
                                // hides the loader after completion of request, whether successfull or failor.             
                                complete: function (e) {
                                    console.log(e)
                                }
                            });
                        });
                    },
                    error: function() {
                        $(".results").append('Error al cargar las ramas.');
                    }
                });
            });
        });

        $(".borrador").click(e => {
            $(".results").html("")
        });
        $("a[data-type='instalink']").click(e => {
            e.preventDefault();
            let vtlib = $(e.currentTarget).data("vtlib");
            console.log(vtlib)
            $.ajax({
                url: "LPI.php",
                type: 'POST',
                dataType: 'json',
                data: { vtlib },
                // shows the loader element before sending.
                beforeSend: function () {
                },
                // hides the loader after completion of request, whether successfull or failor.             
                complete: function (e) {
                    // console.log(e.responseText);
                    // $.toast(e.responseText, {enableHtml: true})
                    let results = e.responseText.split('\n');
                    for (let i = 0; i < results.length; i++) {
                        $(".results").append("<div>" + results[i] + "<div>")
                    }
                    $(".results").parent().animate({ scrollTop: $(".results")[0].scrollHeight }, 500);
                }
            });
        })
    </script>
</body>

</html>