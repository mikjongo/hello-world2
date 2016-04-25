<?php
require_once("classes.php");
//error_reporting(E_ALL);
//var_dump($_REQUEST);

$ctools     = new Tools();
echo(phpversion());
?>

<html>
<meta charset="utf-8">
<title>Search Google</title>
<body>
<p>
stamp:<? echo $ctools->getTimeStamp()?>
 &nbsp;<a href="t1.php">t1</a>
| <a href="search_results.php">search results</a>
    | <a href="phpinfo.php">phpinfo</a>
</p>

<!-- form -->
<form action = "<?php $_PHP_SELF ?>" method = "POST">
    Name: <input type = "text" name = "name" value="<? echo $ctools->getVar("name")?>"/>
    <input type = "submit" name="btn1" value="parse google"/>
</form>

<br>searchterm: <? echo $ctools->getVar("name")?>

<?
$cgoogle    = new GoogleParser($ctools);
if(isset($_POST['btn1'])){
    $cgoogle->parseGoogle($ctools->getVar("name"));
    echo('</br>recs written: '.$cgoogle->getRecsWrittenCount());
}
?>

</body>
</html>
