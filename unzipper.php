<?php
/**
 * O Unzipper extrai arquivos .zip ou .rar e arquivos .gz em webservers.
 * É útil se você não tiver acesso a conchas. Por exemplo, se você quiser carregar muito
 * de arquivos (php framework ou coleção de imagens) como um arquivo para economizar tempo.
 * A partir da versão 0.1.0, ele também suporta a criação de arquivos.
 */

define('VERSION', '0.1.1');

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  //Verifique se um arquivo foi selecionado para descompactar.
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}

if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  // Resultado zipfile, por exemplo, zíper-2016-07-23-11-55.zip.
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}

$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);

/**
 * Unzipper classe
 */
class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();

  public function __construct() {
    // Le diretórios e escolha .zip, .rar e arquivos .gz.
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);

      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => '.zip or .gz or .rar files found, ready for extraction');
      }
      else {
        $GLOBALS['status'] = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
      }
    }
  }

/**
   * Prepare e verifique o arquivo zip para extração.
*
   * $archive de cordas @param
   * O nome do arquivo, incluindo extensão de arquivo. Por exemplo, my_archive.zip.
   * $destination de cordas @param
   * O caminho relativo de destino onde extrair arquivos.
   */
  public function prepareExtraction($archive, $destination = '') {
    // Determina caminhos.
    if (empty($destination)) {
      $extpath = $this->localdir;
    }
    else {
      $extpath = $this->localdir . '/' . $destination;
      // Todo: mova isso para a função de extração.
      if (!is_dir($extpath)) {
        mkdir($extpath);
      }
    }
    // Apenas arquivos locais existentes podem ser extraídos.
    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }
  }

  /**
   * Verifica a extensão do arquivo e chama funções de extrator adequadas.
   *
   * $archive de cordas @param
   * O nome do arquivo, incluindo extensão de arquivo. Por exemplo, my_archive.zip.
   * $destination de cordas @param
   * O caminho relativo de destino onde extrair arquivos.
   */
  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);
    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }

  }

  /**
   * Descomprima/extive um arquivo zip usando ZipArchive.
   *
   *@param $archive
   *@param $destination
   */
  public static function extractZipArchive($archive, $destination) {
    // Verifica se o servidor da web suporta descompactar.
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support unzip functionality.');
      return;
    }

    $zip = new ZipArchive;

    // Verifica se o arquivo é legível.
    if ($zip->open($archive) === TRUE) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Files unzipped successfully');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .zip archive.');
    }
  }

  /**
   * Descomprima um arquivo .gz.
   *
   * $archive de cordas @param
   * O nome do arquivo, incluindo extensão de arquivo. Por exemplo, my_archive.zip.
   * $destination de cordas @param
   * O caminho relativo de destino onde extrair arquivos.
   */
  public static function extractGzipFile($archive, $destination) {
    // Verifique se o zlib está ativado
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP has no zlib support enabled.');
      return;
    }

    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($destination . '/' . $filename, "w");

    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string, strlen($string));
    }
    gzclose($gzipped);
    fclose($file);

    // Verifica se o arquivo foi extraído.
    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['status'] = array('success' => 'File unzipped successfully.');

      // Se tivéssemos um arquivo .gz piche, vamos extrair o arquivo do piche.
      if (pathinfo($destination . '/' . $filename, PATHINFO_EXTENSION) == 'tar') {
        $phar = new PharData($destination . '/' . $filename);
        if ($phar->extractTo($destination)) {
          $GLOBALS['status'] = array('success' => 'Extracted tar.gz archive successfully.');
          // Deleta .tar.
          unlink($destination . '/' . $filename);
        }
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error unzipping file.');
    }

  }

  /**
   * Descomprima/extraia um arquivo Rar usando RarArchive.
   *
   * $archive de cordas @param
   * O nome do arquivo, incluindo extensão de arquivo. Por exemplo, my_archive.zip.
   * $destination de cordas @param
   * O caminho relativo de destino onde extrair arquivos.
   */
  public static function extractRarArchive($archive, $destination) {
    // Verifica se o servidor da web suporta descompactar.
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
      return;
    }
    // Verifique se o arquivo é legível.
    if ($rar = RarArchive::open($archive)) {
      // Verifica se o destino é gravável
      if (is_writeable($destination . '/')) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['status'] = array('success' => 'Files extracted successfully.');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Error: Directory not writeable by webserver.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error: Cannot read .rar archive.');
    }
  }

}

/**
 * Zíper de classe
 *
 * http://at2.php.net/manual/en/class.ziparchive.php#110719
 */
class Zipper {
  /**
   * Adicionar arquivos e subsatratos em uma pasta para zip arquivo.
   *
   * $folder de corda @param
   * Caminho para pasta que deve ser fechada.
   *
   * @param $zipFile ZipArchive
   * Zipfile onde os arquivos acabam.
   *
   *@param int $exclusiveLength
   * Número de texto a ser exclusivo do caminho do arquivo.
   */
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);

    while (FALSE !== $f = readdir($handle)) {
      // Verifica se há caminho local/pai ou arquivo zipping em si e pule.
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        // Remove o prefixo do caminho do arquivo antes de adicionar ao zip.
        $localPath = substr($filePath, $exclusiveLength);

        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        }
        elseif (is_dir($filePath)) {
          // Adiciona sub-diretório.
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }

  /**
   * Fecha uma pasta (incluindo a si mesmo).
   *
   * Uso:
   * Zíper::zipDir ('path/to/sourceDir', 'path/to/out.zip');
   *
   * $sourcePath de cordas @param
   * Caminho relativo do diretório a ser fechado.
   *
   * $outZipPath de corda @param
   * Caminho relativo do arquivo zip de saída resultante.
   */
  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];

    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);
    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    }
    else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();

    $GLOBALS['status'] = array('success' => 'Successfully created archive ' . $outZipPath);
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>File Unzipper + Zipper</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <style type="text/css">
    <!--
    body {
      font-family: Arial, sans-serif;
      line-height: 150%;
    }

    label {
      display: block;
      margin-top: 20px;
    }

    fieldset {
      border: 0;
      background-color: #EEE;
      margin: 10px 0 10px 0;
    }

    .select {
      padding: 5px;
      font-size: 110%;
    }

    .status {
      margin: 0;
      margin-bottom: 20px;
      padding: 10px;
      font-size: 80%;
      background: #EEE;
      border: 1px dotted #DDD;
    }

    .status--ERROR {
      background-color: red;
      color: white;
      font-size: 120%;
    }

    .status--SUCCESS {
      background-color: green;
      font-weight: bold;
      color: white;
      font-size: 120%
    }

    .small {
      font-size: 0.7rem;
      font-weight: normal;
    }

    .version {
      font-size: 80%;
    }

    .form-field {
      border: 1px solid #AAA;
      padding: 8px;
      width: 280px;
    }

    .info {
      margin-top: 0;
      font-size: 80%;
      color: #777;
    }

    .submit {
      background-color: #378de5;
      border: 0;
      color: #ffffff;
      font-size: 15px;
      padding: 10px 24px;
      margin: 20px 0 20px 0;
      text-decoration: none;
    }

    .submit:hover {
      background-color: #2c6db2;
      cursor: pointer;
    }
    -->
  </style>
</head>
<body>
<p class="status status--<?php echo strtoupper(key($GLOBALS['status'])); ?>">
  Status: <?php echo reset($GLOBALS['status']); ?><br/>
  <span class="small">Processing Time: <?php echo $time; ?> seconds</span>
</p>
<form action="" method="POST">
  <fieldset>
    <h1>Archive Unzipper</h1>
    <label for="zipfile">Select .zip or .rar archive or .gz file you want to extract:</label>
    <select name="zipfile" size="1" class="select">
      <?php foreach ($unzipper->zipfiles as $zip) {
        echo "<option>$zip</option>";
      }
      ?>
    </select>
    <label for="extpath">Extraction path (optional):</label>
    <input type="text" name="extpath" class="form-field" />
    <p class="info">Enter extraction path without leading or trailing slashes (e.g. "mypath"). If left empty current directory will be used.</p>
    <input type="submit" name="dounzip" class="submit" value="Unzip Archive"/>
  </fieldset>

  <fieldset>
    <h1>Archive Zipper</h1>
    <label for="zippath">Path that should be zipped (optional):</label>
    <input type="text" name="zippath" class="form-field" />
    <p class="info">Enter path to be zipped without leading or trailing slashes (e.g. "zippath"). If left empty current directory will be used.</p>
    <input type="submit" name="dozip" class="submit" value="Zip Archive"/>
  </fieldset>
</form>
<p class="version">Unzipper version: <?php echo VERSION; ?></p>
</body>
</html>
