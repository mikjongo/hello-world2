<?
/**
 * Class PDO
 * @Requires: C_PDO_CONN
 * @RequiredBy: --.
 * todo make protected ? only from DBIO ?
 */
class DBIO{
    private $dbh,$dbName,$dbConnInfo;
    private $mmOpts = [];                               // see fInitOpts()
    function __construct($dbtype = 'mysql'){
        $oconn  		= new C_PDO_CONN();
        $oinfo	 		= $oconn->fgetConnInfo();
        $this->dbConnInfo = $oinfo;
        $host			= $oinfo['db.host'];
        $dbname			= $oinfo['db.basename'];
        $user			= $oinfo['db.user'];
        $pwd			= $oinfo['db.password'];
        if($dbtype == 'mysql'){
            $vct 		= "mysql:host=$host;dbname=$dbname";
            echo $vct;
            try{
                //fb("conninfo: $vct");
                //MYSQL_ATTR_FOUND_ROWS see:http://www.php.net/manual/en/pdostatement.rowcount.php#104930
                //MYSQL_ATTR_INIT_COMMAND !!! onmisbaar, essential to work with utf-8 !!!
                $this->dbh 	=  new PDO($vct,$user,$pwd, array(PDO::MYSQL_ATTR_FOUND_ROWS => true,PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
                //http://www.php.net/manual/en/pdo.exec.php#99723
                $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $this->dbName = $dbname;

                $this->mmOpts['accid']  =   null;
            }catch(Exception $e){
                throw new Exception('C_PDO_CORE :: Error connecting: '.$e->getMessage());
            }
        }
    }

    function checkTableExists($tblName){
        $sql = "SHOW TABLES LIKE '".$tblName."'";
        $res = $this->dbh->query($sql)->rowCount() > 0;
        return $res;
    }
    /*
     * todo: move to GoogleParser or suppyl the sql to here...
     */
    function createTable($tblName){
        try{
            $sql = "CREATE TABLE IF NOT EXISTS ". $tblName." (
                  xid int(11) NOT NULL AUTO_INCREMENT,
                  xkey tinytext NOT NULL,
                  xvalue text NOT NULL,
                  PRIMARY KEY (xid)
                ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=71349 ;";
            $res = $this->dbh->exec($sql);
        }catch(PDOException $e){
            die('--- died --- createTable() error = '.$e->getMessage());
        }
        return true;
    }
    function getRecSet($sql,$args = array()){
        $stp = $this->dbh->prepare($sql);
        $stp->execute($args);
        return $stp->fetchAll(PDO::FETCH_ASSOC);
    }
    function execute($sql,$args = array()){
        try{
            $sth = $this->dbh->prepare($sql);
            $sth->execute($args);
        }catch(PDOException $e){
            die('--- died --- execute() error = '.$e->getMessage());
        }

        return true;
    }

}

/**
 * Class C_PDO_CONN
 * @RequiredBy: DBIO
 * kk88 make protected so only to be called by DBIO
 */
class C_PDO_CONN{
    private $cfg = array();
    function __construct(){
        $tmp = $_SERVER['SERVER_NAME'];

        if(stripos($tmp, 'djangio') === false){
            $this->cfg['db.host'] 		= 'localhost';
            $this->cfg['db.port'] 		= 1;
            $this->cfg['db.basename'] 	= 'a1655186_1';
            $this->cfg['db.user'] 		= 'root';
            $this->cfg['db.password'] 	= '';
        }
    }
    function fgetConnInfo(){
        return $this->cfg;
    }
}//end c_conn
/**
 * Class GoogleParse
 * @Requires: DBIO, DomDoc, Tools
 */
class GoogleParser{
    //$urlBase = "http://www.google.com/search?output=search&q=india&num=18";
    const TABLENAME     = 'google_scrape';
    const RPP           = 15;
    const NUM_RESULTS   = 99;

    private $cdom       = null;
    private $ctools     = null;
    private $url        = null;
    private $mm         = [];           //modals

    function __construct($ctools){
        $this->ctools   = $ctools;
        $this->cdom     = new DomDoc();
        $this->cdb      = new DBIO();

        $this->mm['recsWritten']    = 0;
        $this->mm['searchTerm']     = '';

        $this->createTableIfNeeded();
    }

    /*
     * @Note: switches to google.nl... todo
     */
    function parseGoogle($searchTerm){
        $searchTerm = $this->ctools->correctUrl($searchTerm);
        $this->mm['searchTerm']     = $searchTerm;
        $this->url  = "http://www.google.com/search?output=search&q=".$searchTerm."&num=" . self::NUM_RESULTS;
        $gredirect  = $this->getGRedirect($this->url);
        $resultPage = $this->ctools->getCurl($gredirect);

        $domdoc = new DOMDocument();
        if(@$domdoc->loadHTML($resultPage)){
            //parsing-lite
            $classname = "g";
            $xpath = new DOMXPath($domdoc);
            $results = $xpath->query("//*[@class='" . $classname . "']");
            $this->mm['recsWritten'] = $results->length;
            $this->writeResults2DB($results,$searchTerm);
        }else{
            die("--died-- Unable to load in DOM:".$this->url);
        }
        return $results;
    }
    function getRecsWrittenCount(){
        return $this->mm['recsWritten'];
    }
    /**************************************/
    /************* b: dbio ****************/
    /**************************************/
    //todo ? move dbio procs to seperate class ?
    function getSearchedTerms(){
        $sql    = 'select distinct xkey from '.self::TABLENAME.' order by xkey';
        $items  = $this->cdb->getRecSet($sql,[]);

        $n      = count($items);
        $keys   = [];
        for($x=0;$x<$n;$x++){
            $key    = $items[$x]['xkey'];
            $keys[] = $key;
        }
        return $keys;
    }

    /**
     * todo: now retrieves whole recset for paging logic, better is count and then work with LIMIT offset count...
     */
    function getSearchResults($searchTerm,$gotoPage){
        $sql    = 'select xkey,xvalue from '.self::TABLENAME.' where xkey = ? ';
        $rs     = $this->cdb->getRecSet($sql,[$searchTerm]);
        $rs     = $this->ctools->pagingLogic($rs,$gotoPage,self::RPP);
        return $rs;
    }
    private function createTableIfNeeded(){
        $bExists = $this->cdb->checkTableExists(self::TABLENAME);
        if(!$bExists){
            $this->ctools->log('CREATING...'.self::TABLENAME);
            $res = $this->cdb->createTable(self::TABLENAME);
            $this->ctools->log('');
            var_dump($res);
            $this->ctools->log('');
            if($this->cdb->checkTableExists(self::TABLENAME)){
                $this->ctools->log(" CREATED:".self::TABLENAME);
            }else{
                $this->ctools->log(" ERROR CREATING:".self::TABLENAME);
            }
        }
    }
    private function writeResults2DB($items,$searchTerm){
        //we'll delete the searchterm recs  first
        $sql = 'delete from '.self::TABLENAME.' where xkey = ?';
        $res = $this->cdb->execute($sql,array($searchTerm));

        //then insert
        $sql = 'insert into '.self::TABLENAME.' (xkey,xvalue) values (?,?)';
        foreach($items as $item){
            $res = $this->cdb->execute($sql,array($searchTerm,$item->nodeValue));
        }
    }
    /**************************************/
    /************* e: dbio ****************/
    /**************************************/
    function displayTest($resultNodes){
        $q = 0;
        if ($resultNodes->length > 0) {
            foreach($resultNodes as $item){
                echo '<br>----'.$q.'-- ';
                echo $review = $item->nodeValue;
                // echo $domdoc->saveHtml($item);
                $q++;
            }
        }else{
            echo ("no results");
        }
    }
    private function getGRedirect($url){
        $data = $this->ctools->getCurl($url);
        $domdoc = new DOMDocument();
        if(@$domdoc->loadHTML($data)){
            $anchors = $this->cdom->getAnchorItems($domdoc);
            $gref = $anchors[0]["href"];
        }else{
            die("--died-- getGRedirect() Unable to load in DOM:".$url);
        }
        return $gref;
    }
}
// atm used for getting google redirect...
class DomDoc{
    function getAnchorItems($dom){
        $link_list=$dom->getElementsByTagName('a');
        $coll = [];
        foreach ($link_list as $node) {
            $aa = $this->makeAnchorItem($dom,$node);
            $coll[] = $aa;
        }
        return $coll;
    }
    //http://stackoverflow.com/questions/3820666/grabbing-the-href-attribute-of-an-a-element/3820783#3820783
    private function makeAnchorItem($dom,$node){
        $aa = [];
        $aa['outer']    = $dom->saveHtml($node);
        $aa['text']     = $node->nodeValue;
        $aa['href']     = "";
        if($node->hasAttribute( 'href' )){
            $aa['href'] = $node->getAttribute( 'href' );
        }
        return $aa;
    }
}
class Tools {
    /*
     * recs as returned by DBIO->getRecSet(). PDO::FETCH_ASSOC
    */
    function recSet2HTMLTable($fds=array(),$recs){
        $nrecs  = count($recs);
        $nfds   = count($fds);
        $t      = '<table>';
        for($irows=0; $irows<$nrecs; $irows++){
            if($irows==0){
                $t.= '<thead>';
                $t.= '<tr>';
                for($ifd=0; $ifd<$nfds; $ifd++){
                    $t.= '<th>'.$fds[$ifd].'</th>';
                }
                $t.='</tr>';
                $t.= '</thead>';
            }
            $t.= '<tr>';
            $rec = $recs[$irows];
            for($ifd=0;$ifd<$nfds;$ifd++){
                $t.= '<td>'.$rec[$fds[$ifd]].'</td>';
            }
            $t.='</tr>';
        }
        $t      .= '</table>';
        return $t;

    }
    function pagingLogic($recset,$page,$rpp){
        $qrecs          = count($recset);

        $out            = [];
        $out['qrecs']   = $qrecs;
        $out['pages']   = ceil($qrecs/$rpp);
        $out['rpp']     = $rpp;

        //page, xstart, xend logic
        if($page<0){                    //for cycling to last
            $page = $out['pages']-1;    //this means last page...
            if($page<0){$page=0;}       //handles norec case
        }

        $xstart = $page * $rpp;
        if($xstart>=$qrecs){            //for cycling to first
            $xstart=0;
            $page = 0;
        }

        $xend = $xstart + $rpp;
        if($xend>=$qrecs){
            $xend=$qrecs;
        }

        $out['page']    = $page;
        $out['from']    = $xstart;
        $out['to']      = $xend-1;


        //fill array
        $aa     = $recset;
        $aarecs = [];
        for($x=$xstart;$x<$xend;$x++){
            $aarecs[] = $aa[$x];
        }
        $out['recs']   = $aarecs;

        return $out;
    }
    function correctUrl($in){
        $in = str_replace(' ','+',$in); // space is a +
        return $in;
    }
    function getTimeStamp(){
        $date = new DateTime();
        return $date->getTimestamp();
    }
    function getVar($varName){
        $res = "";
        if(isset($_REQUEST[$varName])){
            $res = $_REQUEST[$varName];
        }
        return $res;
    }
    //basic, no header settings etc
    function getCurl($url){

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $data=curl_exec($curl);

        curl_close($curl);
        return $data;
    }
    function makeSelect($name,$valueList,$txtList){
        $t      = "<select name='".$name."'>";
        //$posted = isset($_POST[$name]) ? $_POST[$name] : null;
        $posted = isset($_REQUEST[$name]) ? $_REQUEST[$name] : null;
        $n      = count($valueList);
        for($i=0;$i<$n;$i++){
            $val    = $valueList[$i];
            $txt    = $txtList[$i];
            if($posted && ($val == $posted)){
                $t      .= "<option value='".$val."' selected='selected'>".$txt."</option>";
            }else{
                $t      .= "<option value='".$val."'>".$txt."</option>";
            }
        }
        $t.="</select>";
        return $t;
    }
    function log($msg){
        echo('</br>'.$msg);
    }
}
?>