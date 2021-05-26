<?php

namespace josecarlosphp\simpleapp;

class App
{
    /**
     * @var boolean
     */
    static protected $initYet = false;
    /**
     * @var boolean
     */
    private $debug;
    /**
     * @var string
     */
    private $clientIp;

    static protected $headerDone = false;

    static public $parola = '';

    static public $sistema = 'Desconocido';

    static public $dbhost = 'localhost';
    static public $dbport = 3306;
    static public $dbname = 'dbname';
    static public $dbuser = 'dbuser';
    static public $dbpass = 'dbpass';
    static public $charset = 'UTF-8';

    static public $baseurl = '/';
    static public $friendlyurls = true;

    static public $defaultOp = '';

    static protected $name = 'SimpleApp';
    static protected $icon = 'cloud';

    public function __construct($debug=false)
    {
        self::init();

        $this->debug($debug);
    }

    public function getClientIp()
    {
        if(empty($this->clientIp))
        {
            $this->clientIp = GetClientIP();
        }

        return $this->clientIp;
    }

    static public function init()
    {
        if (!self::$initYet) {
            error_reporting(E_ALL);
            @ini_set('display_errors', 1);

            if (function_exists('mb_substr')) {
                mb_internal_encoding('UTF-8');
            } else {
                function mb_substr($str, $a, $b=null){return substr($str, $a, $b);}
                function mb_strpos($str, $a, $b=null){return strpos($str, $a, $b);}
                function mb_strtoupper($str){return strtoupper($str);}
                function mb_strtolower($str){return strtolower($str);}
            }

            define('PI_FLAGS_HTML', defined('ENT_XHTML') ? (ENT_COMPAT | ENT_XHTML) : (ENT_COMPAT)); // defined('ENT_HTML5') ? (ENT_COMPAT | ENT_HTML5) : (ENT_COMPAT | ENT_HTML401));
            define('PI_ENCODING', 'UTF-8');

            require 'inc/classes/MyApp.class.php';
            require 'inc/classes/MyCache.class.php';
            require 'vendor/autoload.php';
            require 'vendor/josecarlosphp/functions/src/files.php';
            require 'vendor/josecarlosphp/functions/src/internet.php';
            //require 'vendor/josecarlosphp/functions/src/arrays.php';

            if (!is_file('config/config.inc.php') && is_file('config/config-sample.inc.php')) {
                copy('config/config-sample.inc.php', 'config/config.inc.php');
            }

            if (is_file('config/config.inc.php')) {
                require 'config/config.inc.php';
            } else {
                self::die(500, 'ERROR: Missing config file.');
            }
        }
    }

    static public function exit($code, $msg)
    {
        self::die($code, $msg);
    }

    static public function die($code, $msg)
    {
        http_response_code($code);
        echo $msg;
        exit;
    }

    public function run()
    {
        global $app; //Para que esté disponible

        if (empty($app)) {
            $app = $this; //Por si la variable se ha declarado con otro nombre
        }

        self::AutoConfig();

        session_start();

        if (self::$parola) {
            if (isset($_POST['logout'])) {
                unset($_SESSION['autenticado']);
            }

            if (isset($_POST['que'])) {
                $_SESSION['autenticado'] = ($_POST['que'] == self::$parola);
            }
        } else {
            $_SESSION['autenticado'] = true;
        }

        if (!empty($_GET['download']) && self::isLoggedIn()) {
            self::DescargarArchivo($_GET['download']);
        }

        self::Header();

        if (self::isLoggedIn()) {
            global $db;

            $db = self::createDatabaseConnection();

            $op = self::getCurrentOp();

            switch ($op) {
                case 'logout':
                    $_SESSION["autenticado"] = false;
                    self::Msg('Sesión cerrada', 'check');
                    break;
                default:
                    if ($op) {
                        $file = 'inc/ops/'.$op.'.inc.php';
                        if (is_file($file)) {
                            global $pi_link;

                            $pi_link = self::getLink($op);

                            include($file);
                        } else {
                            self::Msg('No se encuentra '.self::HtmlEntities($op), 'error');
                        }
                    }
                    break;
            }

            if (isset($db) && is_object($db)) {
                $db->Close();
            }
        } else {
            ?>
            <form action="<?php echo self::$baseurl; ?>" method="post">
                <table class="table table-lg">
                    <tr>
                        <td class="text-right h3">Login</td>
                        <td><input type="password" name="que" id="que" size="20" maxlength="20" class="form-control form-control-lg" /></td>
                        <td><input type="submit" value="Aceptar" class="btn btn-lg btn-primary" /></td>
                    </tr>
                </table>
            </form>
            <?php
        }

        self::Footer();

        exit;
    }

    static public function isLoggedIn()
    {
        return !empty($_SESSION['autenticado']);
    }

    static public function getCurrentOp()
    {
        return isset($_GET['op']) ? str_replace('.', '', $_GET['op']) : (isset($_GET['page0']) ? str_replace('.', '', $_GET['page0']) : self::$defaultOp);
    }

    static public function getOps()
    {
        $ops = array();
        $files = getFilesExt('inc/ops', array('php'));
        sort($files);
        foreach ($files as $file) {
            if (substr($file, -8) == '.inc.php') {
                $ops[substr($file, 0, -8)] = ucfirst(substr($file, 0, -8));
            }
        }

        return $ops;
    }

    static public function createDatabaseConnection()
    {
        if (self::$dbhost) {
            $db = \josecarlosphp\db\DbConnection::Factory(self::$dbhost, self::$dbport, self::$dbname, self::$dbuser, self::$dbpass, false, self::$charset);
            $db->Connect() or self::die(500, 'ERROR: Can not connect to database<br />'.$db->Error()); //.sprintf('<br /><br />dbhost = %s<br />dbport = %s<br />dbname = %s<br />dbuser = %s<br />dbpass = %s<br />', self::$dbhost, self::$dbport, self::$dbname, self::$dbuser, self::$dbpass));

            return $db;
        }

        return false;
    }

    public function getLink($op=null)
    {
        return sprintf(self::$friendlyurls ? '%s/' : 'index.php?op=%s', $op);
    }

    public function debug($debug=null)
    {
        if (!is_null($debug)) {
            $this->debug = $debug ? true : false;
        }

        return $this->debug;
    }

    static public function Header()
    {
        ?>
<!doctype html>
<html lang="es">
<head>
<base href="<?php echo self::$baseurl; ?>" />
<meta charset="utf-8">
<title><?php echo self::$name; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="Robots" content="none" />
<link rel="shortcut icon" href="favicon.ico" />
<link rel="icon" href="favicon.ico" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous" media="all" />
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous" media="all" />
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script type="text/javascript" src="js/Validador.js"></script>
<script type="text/javascript">
var validador = new Validador();
validador.cfgFormatoDeFecha = 'yyyy-mm-dd';
</script>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar d-print-none" id="headerbox">
        <h1 id="logobox">
            <a href="<?php echo self::$baseurl; ?>"><i class="fas fa-<?php echo self::$icon; ?>"></i> <?php echo self::$name; ?></a>
        </h1>
        <button class="navbar-toggler" type="button" onclick="$('#navbarMain').toggle(200);">
            <span class="navbar-toggler-icon"><i class="fas fa-bars"></i></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav m-auto menu">
            <?php
            if (self::isLoggedIn()) {
                $currentOp = self::getCurrentOp();
                foreach (self::getOps() as $op=>$text) {
                    printf('<li class="nav-item %s"><a class="nav-link" href="%s">%s</a></li>', $op == $currentOp ? 'active' : '', self::getLink($op), $text);
                }
            }
            ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item text-nowrap">
                    <?php echo self::$sistema; ?>
                </li>
            </ul>
        </div>
    </nav>
    <main role="main">
        <div class="container-fluid">
            <div class="">
                <?php
        self::$headerDone = true;
    }

    static public function Footer()
    {
                ?>
            </div>
        </div>
    </main>
    <hr />
    <footer class="sticky-footer">
        <p class="text-center small pt-5 pb-3">
            Funciona con <strong>SimpleApp</strong>, gracias a <a href="https://josecarlosphp.com" title="Programador PHP experto">josecarlosphp.com</a>
        </p>
    </footer>
</body>
</html>
        <?php
    }

    static public function Msg($str, $class='')
    {
        switch($class)
        {
            case 'err':
            case 'error':
                $class = 'danger';
                break;
            case 'check':
            case 'ok':
                $class = 'success';
                break;
            case 'aviso':
            case 'adv':
                $class = 'warning';
                break;
            case '':
                $class = 'info';
                break;
        }

        printf('<div class="alert alert-%s">%s</div>', $class, $str);
        flush();
    }

    static public function Br($n=1)
    {
        for($c=0; $c<$n; $c++)
        {
            echo "<br />";
        }
    }

    static public function Hr()
    {
        echo "<hr />";
    }

    static public function VarExport($var)
    {
        self::Msg('<pre>'.var_export($var, true).'</pre>');
    }

    static public function DescargarArchivo($file)
    {
        if(self::IsDownloadable($file))
        {
            if(self::$headerDone || headers_sent())
            {
                global $pi_link;

                printf('<a href="%s&download=%s" class="btn btn-primary">Descargar</a>', $pi_link, urlencode($file));
            }
            else
            {
                switch(getExtension($file))
                {
                    case 'pdf':
                        header('Content-type: application/pdf');
                        header('Content-disposition: attachment; filename="'.basename($file).'"');
                        header('Content-length: '.(int)filesize($file));
                        readfile($file);
                        break;
                    case 'csv':
                        header('Content-type: text/csv');
                        header('Content-disposition: attachment; filename="'.basename($file).'"');
                        header('Content-length: '.(int)filesize($file));
                        readfile($file);
                        break;
                    case 'xml':
                        header('Content-type: text/xml');
                        header('Content-disposition: attachment; filename="'.basename($file).'"');
                        header('Content-length: '.(int)filesize($file));
                        readfile($file);
                        break;
                    case 'htm':
                    case 'html':
                        header('Content-type: text/html');
                        header('Content-length: '.(int)filesize($file));
                        readfile($file);
                        break;
                    case 'txt':
                        header('Content-type: text/plain');
                        header('Content-length: '.(int)filesize($file));
                        readfile($file);
                        break;
                    case 'zip':
                        header('Content-type: application/zip');
                        //header('Content-length: '.filesize($file));
                        header('Content-disposition: attachment; filename="'.basename($file).'"');
                        header('Content-length: '.(int)filesize($file));
                        readfile($file);
                        break;
                    default:
                        if (basename($file) == 'error_log') {
                            header('Content-type: text/plain');
                        }
                        header('Content-disposition: attachment; filename="'.basename($file).'"');
                        header('Content-length: '.(int)filesize($file));
                        readfile($file);
                        break;
                }
            }

            exit; //!!!!!!!!!!!
        }
        else
        {
            self::Msg('No se puede descargar: '.$file);
        }
    }

    static public function IsDownloadable($file)
    {
        if(is_file($file) && is_readable($file))
        {
            if(in_array(getExtension($file), array('zip', 'sql', 'txt')))
            {
                return true;
            }
        }

        return false;
    }

    static public function HtmlEntities($str)
    {
       return htmlentities($str, PI_FLAGS_HTML, PI_ENCODING);
    }

    static public function HtmlEntityDecode($str)
    {
       return html_entity_decode($str, PI_FLAGS_HTML, PI_ENCODING);
    }

    static public function Icon($icon, $width=16, $noEcho=false, $title="")
    {
        $aux = str_replace('.png', '', $icon); //Por si acaso

        $arr = array(
            'accept' => 'check',
            'remove' => 'ban',
            'gear' => 'cog',
            'process' => 'cog',
            'add' => 'plus',
            'help' => 'question',
            'warning' => 'exclamation-triangle',
            'back' => 'arrow-left',
            'next' => 'arrow-right',
            //'info' => 'info',
            'page' => 'file',
            'books' => 'file-archive',
            //'blank' => 'blank',
            'delete' => 'times',
            //'edit' => 'edit',
            'card' => 'magic',
            'promo' => 'certificate',
            'folder' => 'folder',
            'down' => 'file-download',
            'logout' => 'sign-out-alt',
            'security' => 'shield-alt',
            //'calculator' => 'calculator',
        );
        if(isset($arr[$aux]))
        {
            $aux = $arr[$aux];
        }

        $class = '';
        switch($aux)
        {
            case 'check':
            case 'plus':
            case 'shield-alt':
                $class = 'text-success';
                break;
            case 'ban':
            case 'times':
                $class = 'text-danger';
                break;
            case 'question':
            case 'info':
                $class = 'text-info';
                break;
            case 'exclamation-triangle':
                $class = 'text-warning';
                break;
            case 'calculator':
                $class = 'text-secondary';
                break;
        }

        switch($aux)
        {
            case 'file':
                $aux = sprintf('<i class="far fa-%s%s %s"%s></i>', $aux, $class ? ' '.$class : '', self::Width2ClassSize($width), $title ? ' title="'.$title.'"' : '');
                break;
            case 'twitter':
                $aux = sprintf('<i class="fab fa-%s%s %s"%s></i>', $aux, $class ? ' '.$class : '', self::Width2ClassSize($width), $title ? ' title="'.$title.'"' : '');
                break;
            default:
                $aux = sprintf('<i class="fas fa-%s%s %s"%s></i>', $aux, $class ? ' '.$class : '', self::Width2ClassSize($width), $title ? ' title="'.$title.'"' : '');
                break;
        }

        if(!$noEcho)
        {
            echo $aux;
        }

        return $aux;
    }

    static public function Width2ClassSize($width)
    {
        switch((int)$width)
        {
            case 24:
                return 'fa-lg';
            case 32:
                return 'fa-2x';
            case 48:
                return 'fa-3x';
            case 64:
                return 'fa-5x';
            case 128:
                return 'fa-10x';
        }

        return '';
    }

    static public function AutoConfig()
    {
        if(self::$sistema == 'auto')
        {
            self::$sistema = 'Desconocido';

            $cfg17 = '../app/config/parameters.php';
            $cfgPS = '../config/settings.inc.php';
            $cfgWP = '../wp-config.php';
            $cfgSW = '../config/config-SWPHP.inc.php';
            $cfgSS = '../inc/config.inc.php';
            $cfgJL = '../configuration.php';
            $cfgMG = '../merkagest/bd.cfg';
            $cfgIC = '../definedirs.php';
            $cfgAD = '../Connections/adayss.php';
            $cfgPT = '../php/bd/DataBase.plib';

            if(is_file($cfg17))
            {
                self::$sistema = 'PrestaShop 1.7 o superior';

                $config = include_once($cfg17);

                self::$dbhost = $config['parameters']['database_host'];
                self::$dbport = $config['parameters']['database_port'];
                self::$dbname = $config['parameters']['database_name'];
                self::$dbuser = $config['parameters']['database_user'];
                self::$dbpass = $config['parameters']['database_password'];
                self::$charset = 'UTF-8';
            }
            elseif(is_file($cfgPS))
            {
                self::$sistema = 'PrestaShop anterior a 1.7';

                include_once($cfgPS);

                self::$dbhost = _DB_SERVER_;
                self::$dbport = 3306;
                self::$dbname = _DB_NAME_;
                self::$dbuser = _DB_USER_;
                self::$dbpass = _DB_PASSWD_;
                self::$charset = 'UTF-8';
            }
            elseif(is_file($cfgWP))
            {
                self::$sistema = 'WordPress';

                include_once($cfgWP);

                self::$dbhost = DB_HOST;
                self::$dbport = 3306;
                self::$dbname = DB_NAME;
                self::$dbuser = DB_USER;
                self::$dbpass = DB_PASSWORD;
                self::$charset = DB_CHARSET;
            }
            elseif(is_file($cfgSW))
            {
                self::$sistema = 'Simple web PHP (EA)';

                include_once($cfgSW);

                self::$dbhost = SWPHP_DBHOST;
                self::$dbport = SWPHP_DBPORT;
                self::$dbname = SWPHP_DBNAME;
                self::$dbuser = SWPHP_DBUSER;
                self::$dbpass = SWPHP_DBPASS;
                self::$charset = 'UTF-8';
            }
            elseif(is_file($cfgSS))
            {
                self::$sistema = 'Simple web PHP (realmente simple)';

                function t($str){return $str;}

                include_once($cfgSS);

                self::$dbhost = $config['dbhost'];
                self::$dbport = $config['dbport'];
                self::$dbname = $config['dbname'];
                self::$dbuser = $config['dbuser'];
                self::$dbpass = $config['dbpass'];
                self::$charset = defined('INMO_DOMINIO') ? 'ISO-8859-1' : 'UTF-8';
            }
            elseif(is_file($cfgJL))
            {
                self::$sistema = 'Joomla';

                include_once($cfgJL);

                $jconfig = new JConfig();

                self::$dbhost = $jconfig->host;
                self::$dbport = 3306;
                self::$dbname = $jconfig->db;
                self::$dbuser = $jconfig->user;
                self::$dbpass = $jconfig->password;
                self::$charset = 'UTF-8';
            }
            elseif(is_file($cfgMG))
            {
                self::$sistema = 'Merkagest';

                include_once($cfgMG);

                self::$dbhost = CFG_BD_Server;
                self::$dbport = 3306;
                self::$dbname = CFG_BD_BD;
                self::$dbuser = CFG_BD_User;
                self::$dbpass = CFG_BD_Pass;
                self::$charset = UTF8_ACTIVO ? 'UTF-8' : 'ISO-8859-1';
            }
            elseif(is_file($cfgIC))
            {
                self::$sistema = 'Internia CMS';

                include_once($cfgIC);
                $cwd = getcwd();
                chdir('..');
                include_once(FILES_DIR.'configs/config.php');
                chdir($cwd);

                self::$dbhost = DBHOST;
                self::$dbport = DBPORT;
                self::$dbname = DBNAME;
                self::$dbuser = DBUSER;
                self::$dbpass = DBPASS;
                self::$charset = 'ISO-8859-1';
            }
            elseif(is_file($cfgAD))
            {
                self::$sistema = 'Adays';

                include_once($cfgAD);

                self::$dbhost = $hostname_adayss;
                self::$dbport = 3306;
                self::$dbname = $database_adayss;
                self::$dbuser = $username_adayss;
                self::$dbpass = $password_adayss;
            }
            elseif(is_file($cfgPT))
            {
                self::$sistema = 'PT';

                include_once($cfgPT);

                $dataBase = new DataBase();
                self::$dbhost = $dataBase->host;
                self::$dbport = 3306;
                self::$dbname = $dataBase->nombre;
                self::$dbuser = $dataBase->usuario;
                self::$dbpass = $dataBase->password;
                unset($dataBase);
            }
        }

        if(self::$charset == 'utf8')
        {
            self::$charset = 'UTF-8';
        }
    }
}
