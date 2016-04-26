<?php
require_once("classes.php");
//error_reporting(E_ALL);

$ctools     = new Tools();
$cgoogle    = new GoogleParser($ctools);
$mm         = [];                       //modals
$mm['searchClicked']= false;
$mm['pagedRecSet']  = null;

if(isset($_POST['btn1']) || isset($_POST['btn2'])){
    $mm['searchClicked']= true;
    $mm['search']       = $_POST['select1'];
    $mm['gotoPage']     = 0;
    if(isset($_POST['gotoPage'])){
        $mm['gotoPage'] = $_POST['gotoPage'];
    }
    if(isset($_POST['btn1'])){
        $mm['gotoPage'] = $_POST['gotoPage'] = 0;
    }
    $mm['pagedRecSet']  = $cgoogle->getSearchResults($mm['search'], $mm['gotoPage']);
    //var_dump($mm['pagedRecSet']);
}
?>
<html>
<head>
    <meta charset="utf-8">
    <title>searches</title>    <style>
        td{border-top:1px solid lightgrey}
    </style>
</head>
<body>
<!-- header -->
<p>
stamp:<? echo $ctools->getTimeStamp()?>
&nbsp;<a href="t1.php">t1</a>
| <a href="index.php">search</a>
</p>


<!-- form -->
<form action = "<?php $_PHP_SELF ?>" method = "POST">
<?
    $termsList = $cgoogle->getSearchedTerms();

    //termslist select
    if(count($termsList) > 0 ){
        echo('search term:&nbsp;');
        echo ($ctools->makeSelect('select1',$termsList,$termsList));
        echo('&nbsp;&nbsp;');
        echo('<input type="submit" name="btn1" value="Get DB Results" />');
    }else{
        echo( 'no recs in db yet...');
    }

    //paging
    if($mm['searchClicked']){
        $pages      = $mm['pagedRecSet']['pages'];
        $sel2items  = [];
        for($i=0; $i<$pages; $i++){
            $sel2items[] = $i;
        }
        $arr = ['<hr>'
            ,'<p>go to page:&nbsp;'
            , $ctools->makeSelect('gotoPage',$sel2items,$sel2items)
            ,' / ' . $pages
            ,' <input type="submit" name="btn2" value="Go !" />'
            ,'<small><i>&nbsp;&nbsp;'
            ,' recs: '.$mm['pagedRecSet']['from'].' - '.$mm['pagedRecSet']['to']
            ,' &nbsp;&nbsp;rpp: '.$mm['pagedRecSet']['rpp']
            ,' &nbsp;&nbsp;n: '.$mm['pagedRecSet']['qrecs']
            ,'</small></i>'
            ,'</p>'
            ];
        echo(implode($arr,''));
    }

    //make html table
    if($mm['searchClicked']){
        $page   = $mm['pagedRecSet']['page'];
        $recs   = $mm['pagedRecSet']['recs'];
        echo('<hr>');
        echo($ctools->recSet2HTMLTable(['xvalue'],$recs));
    }
?>
</form>
</body>
</html>