<!DOCTYPE HTML>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <center>
</head>
<body>

<?php
include('includes/header.php');
require_once('functions.php');
require_once('/var/webconfig/config.php');

define ("orderClear",0);
define ("orderCheckedAsc",1);
define ("orderCheckedDesc",2);


echo "<details><summary>*</summary>";
echo "<table>";
foreach ($_GET as $key => $value)
{	echo "<tr><td>".$key."</td><td>".$value."</td></tr>";}
echo "</table>";


$conn1 = f_mysqlConnect(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);


// choices for dropdown menu
$sql = "SELECT ID, NAME from CATEGORIES ORDER BY NAME ASC";
if($cat_result = $conn1->query($sql))
{
    while($category_row =$cat_result->fetch_assoc())
    {
        $categoryArray[$category_row['ID']]=$category_row['NAME'];
    }
}



$category = NULL;
$formattedDate = NULL;
$searchOrder = 2;
$pageName = basename($_SERVER['PHP_SELF']); // used so that

$allOrStocked = "showAll";
$incSalesHeaders = $incSalesTable = "";
$sqlAllOrStocked = $orderBy = $sqlCategory ="";


if( $_SERVER['REQUEST_METHOD'] == 'GET')
{

    if( (isset($_GET['menuChoice']) ) )
    {
        $category = f_CleanAndTrim($conn1,$_GET['menuChoice']);

    }

    if(isset($_GET['dateStockCheck']))
    {
        $formattedDate = f_CleanAndTrim($conn1,$_GET['dateStockCheck']);

    }



}
$sqlCategory = "WHERE CATEGORIES.ID = '".$category."'";


if( $_SERVER['REQUEST_METHOD'] == 'POST')
{

    echo "<table>";
    $i=1;
    foreach ($_POST as $key => $value)
    {
        echo "<tr><td>".$key."</td><td>".$value."</td></tr>";
        if(is_array($value))
        {
            echo "<tr><td>Its an array</td><td>".$i."</td></tr>";
            $i++;
            foreach ($value as $inKey => $inValue)
            {
                echo "<tr><td>".$inKey."</td><td>".$inValue."</td></tr>";
                foreach ($inValue as $finKey => $finValue)
                {
                    echo "<tr><td>".$finKey."</td><td>".$finValue."</td></tr>";
                }
            }
        }
    }
    echo "</table>";

    if(isset($_POST['categoryList']))
    {
        $category = f_CleanAndTrim($conn1,$_POST['categoryList']);
        $sqlCategory = "WHERE CATEGORIES.ID = '".$category."'";
    }

    if(isset($_POST['dateStockCheck']))
    {
        $formattedDate = f_CleanAndTrim($conn1,$_POST['dateStockCheck']);

    }

    if(isset($_POST['searchOrder']) && is_numeric($_POST['searchOrder']))
    {
        $searchOrder = f_CleanAndTrim($conn1,$_POST['searchOrder']);


    }

    if(isset($_POST['allOrStocked']))
    {
        $allOrStocked = f_CleanAndTrim($conn1,$_POST['allOrStocked']);
        switch($allOrStocked){
            case "showAll":
                $sqlAllOrStocked = "LEFT";
                break;
            case "showStocked":
                $sqlAllOrStocked = "INNER";
                break;
        }

    }



    if(isset($_POST['submit'])&&($_POST['submit']=="Save") )
    {

        $sql = "
		SELECT PRODUCTS.ID,PRODUCTS.NAME,CATEGORIES.ID AS 'catID',CATEGORIES.NAME AS catName,CODE,stocking.Barcode as 'stocking'
		".$sqlIncSales."
		FROM PRODUCTS
		LEFT JOIN STOCKCURRENT ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
		".$sqlAllOrStocked." JOIN stocking ON PRODUCTS.CODE = stocking.Barcode
		INNER JOIN CATEGORIES ON PRODUCTS.CATEGORY = CATEGORIES.ID 
		".$sqlCategory."
		GROUP BY ID,PRODUCTS.NAME,catID, catName,CODE,stocking
		ORDER BY PRODUCTS.NAME ASC 
		";

        echo "<br><br>".$sql."<br><br>";
        if($result = $conn1->query($sql))
        {
            echo "<div class='fontSmallLight'>Number of rows : ".$result->num_rows."</div>";
            while($row =$result->fetch_assoc())
            {
                $prevArray[$row['CODE']][stocking]=$row['stocking'];
                $prevArray[$row['CODE']][barcode]=$row['CODE'];
                $prevArray[$row['CODE']][category]=$row['catID'];
            }

        }
        else{ echo "<div class='fontBigRed'>Error</div>".$conn1->error;}



        foreach ($_POST as $key => $value)
        {
            if(is_array($value))
            {
                $code= $key;
                $lineEdited = FALSE;
                $stocking = $value[stocking];
                $catID = $value[category];

                echo "<br> CATID = ".$catID;

                $oldStocking = $prevArray[$code][stocking];
                $oldCatID = $prevArray[$code][category];

                // check if anything has changed...

                if(($catID != $oldCatID))
                {
                    f_changeCategory($conn1,$code,$catID);
                    $lineEdited = TRUE;

                }

                if(($stocking != $oldStocking))
                {
                    if(isset($stocking))
                    {
                        $sql = "insert into stocking (Barcode) VALUES ('".$stocking."')";
                        if($result = $conn1->query($sql))
                        {
                            echo "<br>".$sql."<br>";
                        }
                        else{ echo "<div class='fontBigRed'>Error</div>".$conn1->error;}
                    }
                    else
                    {
                        $sql = "DELETE FROM stocking WHERE Barcode = '".$oldStocking."'";
                        if($result = $conn1->query($sql))
                        {
                            echo "<br>".$sql."<br>";
                        }
                        else{ echo "<div class='fontBigRed'>Error</div>".$conn1->error;}
                    }
                    $lineEdited = TRUE;
                }

                if ($lineEdited)
                {
                    $sql = "insert into prodChanged (barcode) VALUES ('".$code."')";
                    if($result = $conn1->query($sql))
                    {
                        echo "<br>".$sql."<br>";
                    }
                    else{ echo "<div class='fontBigRed'>Error</div>".$conn1->error;}
                }

            }
        }

    }





    // show sales information - takes considerably longer to run the query
    if(isset($_POST['incSales']))
    {
        $incSales = f_CleanAndTrim($conn1,$_POST['incSales']);
        if ($incSales == "includeSales")
        {
            echo "<br> INCLUDE SALES <br>";
            $sqlIncSales = ",SUM(if(DATE_FORMAT( STOCKDIARY.DATENEW, '%Y %m' ) =DATE_FORMAT(now(), '%Y %m'),(STOCKDIARY.UNITS),0)) AS 'Month1', 
							SUM(if(DATE_FORMAT( STOCKDIARY.DATENEW, '%Y %m' ) =DATE_FORMAT(now()-INTERVAL 1 MONTH, '%Y %m'),(STOCKDIARY.UNITS),0)) AS 'Month2', 
							SUM(if(DATE_FORMAT( STOCKDIARY.DATENEW, '%Y %m' ) =DATE_FORMAT(now()-INTERVAL 2 MONTH, '%Y %m'),(STOCKDIARY.UNITS),0)) AS 'Month3',
							SUM(if(DATE_FORMAT( STOCKDIARY.DATENEW, '%Y %m' ) =DATE_FORMAT(now()-INTERVAL 3 MONTH, '%Y %m'),(STOCKDIARY.UNITS),0)) AS 'Month4',
							SUM(if(DATE_FORMAT( STOCKDIARY.DATENEW, '%Y %m' ) =DATE_FORMAT(now()-INTERVAL 4 MONTH, '%Y %m'),(STOCKDIARY.UNITS),0)) AS 'Month5' ";

            $sqlIncSales2 = "LEFT JOIN STOCKDIARY ON PRODUCTS.ID = STOCKDIARY.PRODUCT ";
            $incSalesHeaders = "<th><div>Month</div><div>5</div></th>
								<th><div>Month</div><div>4</div></th>
								<th><div>Month</div><div>3</div></th>
								<th><div>Month</div><div>2</div></th>
								<th><div>Month</div><div>1</div></th>";
            // also have to control what to display when outputing the table data

        }
    }


    if(isset($_POST['setToZero'])&&(f_ValidateDate($formattedDate)))
    {

        $sql = "SET SQL_SAFE_UPDATES=0";
        if($result = $conn1->query($sql))
        {
            echo "<br>".$sql."<br>";
        }
        else{ echo "<div class='fontBigRed'>Error</div>".$conn1->error;}

        //echo "<br> category = ".$formattedDate."<br>";
        $sql = 'UPDATE unicenta2016.STOCKCURRENT
				INNER JOIN PRODUCTS ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
				AND PRODUCTS.ID NOT IN 
					( select ID from PRODUCTS
				INNER JOIN stockLastChecked ON stockLastChecked.Barcode = PRODUCTS.CODE AND DATE_FORMAT(stockLastChecked.Date, "%Y-%m-%d") >= "'.$formattedDate.'"
				where PRODUCTS.CATEGORY = "'.$category.'"
				ORDER BY NAME ASC)
				SET unicenta2016.STOCKCURRENT.UNITS = 0        
				WHERE
				PRODUCTS.CATEGORY = "'.$category.'"';

        if($result = $conn1->query($sql))
        {
            echo "<br>".$sql."<br>";
            echo "<div class='fontSmallLight'>Number of rows : ".$result->num_rows."</div>";

            $dt = new DateTime();
            $time = $dt->format('Y-m-d H:i:s');
            $sql2 = "INSERT INTO unicenta2016.catSetZero VALUES(UUID(),'". $category."','" .$time."')";
            $result = $conn1->query($sql2);

        }
        else{ echo "<div class='fontBigRed'>Error</div>".$conn1->error;}

        $sql = "SET SQL_SAFE_UPDATES=1";
        if($result = $conn1->query($sql))
        {
            echo "<br>".$sql."<br>";
        }
        else{ echo "<div class='fontBigRed'>Error</div>".$conn1->error;}

    }
    else{
        echo"<br><br> You must choose a valid date <br><br>";
    }
}
$sqlOrder = "ORDER BY ".$orderBy;



switch($searchOrder){
    case orderCheckedAsc:
        echo "orderCheckedAsc";
        $orderBy = "str_to_date(checkedDate, '%d-%M-%Y') ASC,";
        break;
    case orderCheckedDesc:
        echo "orderCheckedDesc";
        $orderBy = "str_to_date(checkedDate, '%d-%M-%Y') DESC,";
        break;
    case orderClear:
        echo "orderClear";
        $orderBy = "";
        break;


}



#inner JOIN STOCKDIARY ON (PRODUCTS.ID = STOCKDIARY.PRODUCT AND DATE_FORMAT( STOCKDIARY.DATENEW, '%Y %m %d' ) BETWEEN DATE_FORMAT(now()-INTERVAL 5 MONTH, '%Y %m %d') AND DATE_FORMAT(now(), '%Y %m %d'))

$sql = "
SELECT PRODUCTS.ID,PRODUCTS.NAME,CATEGORIES.ID AS catID,CATEGORIES.NAME AS catName,CODE,STOCKCURRENT.UNITS,suppliers.Supplier,PRICEBUY,PRICESELL,SupplierCode,
DATE_FORMAT(stockLastChecked.Date, '%d-%M-%Y') as 'checkedDate', stocking.Barcode as 'stocking'
".$sqlIncSales."
FROM PRODUCTS
LEFT JOIN STOCKCURRENT ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
LEFT JOIN supplier_link ON PRODUCTS.CODE = supplier_link.Barcode
LEFT JOIN suppliers ON (CAST(supplier_link.SupplierID AS CHAR) = CAST(suppliers.SupplierID AS CHAR))
LEFT JOIN stockLastChecked ON stockLastChecked.Barcode = PRODUCTS.CODE
".$sqlAllOrStocked." JOIN stocking ON PRODUCTS.CODE = stocking.Barcode
INNER JOIN CATEGORIES ON PRODUCTS.CATEGORY = CATEGORIES.ID
".$sqlIncSales2." 
".$sqlCategory."
GROUP BY ID,PRODUCTS.NAME, catName,CODE, STOCKCURRENT.UNITS,Supplier,CaseUnits,PRICEBUY,PRICESELL,SupplierCode,checkedDate,stocking
ORDER BY ".$orderBy." PRODUCTS.NAME ASC 
";
echo " SQL = ".$sql."<br>";

echo "</details>";

// Drop down menu to select the Category
echo'<form action="stock_by_category_REVIEW2.php" method="post">';

echo '<select name ="categoryList">';
echo '<option disabled selected value> - choose category - </option>';

foreach($categoryArray as $catID => $catName)
{
    if ($catID == $category){$selected_value = 'selected="selected"';}else{$selected_value = "";}
    echo '<option '.$selected_value.' value= "' . $catID .'">' . $catName . '</option>';
}
echo '</select>';






// Drop down menu to select the order
echo '<select name ="searchOrder">';
echo '<option disabled selected value> - order by - </option>';

if ($searchOrder == orderCheckedDesc)
{$selected_value = 'selected="selected"'; $searchStrDate = "checkedDate Desc,";}else{$selected_value = "";}
echo '<option '.$selected_value.' value= '.orderCheckedDesc.' >Last Checked</option>';

if ($searchOrder == orderCheckedAsc)
{$selected_value = 'selected="selected"'; $searchStrDate = "checkedDate ASC,";}else{$selected_value = "";}
echo '<option '.$selected_value.' value= '.orderCheckedAsc.' >Oldest Checked</option>';

if ($searchOrder == orderClear)
{$selected_value = 'selected="selected"'; $searchStrDate = "";}else{$selected_value = "";}
echo '<option '.$selected_value.' value= '.orderClear.' >Clear</option>';

echo '</select>';
echo $allOrStocked;

// Drop down menu to select ALL products are only STOCKED
echo '<select name="allOrStocked">';
echo '<option disabled selected value=""> - All or Stocked - </option>';

if ($allOrStocked == "showAll") {
    $selected_value = 'selected="selected"';
} else {
    $selected_value = "";
}
echo '<option ' . $selected_value . ' value="showAll">All</option>';

if ($allOrStocked == "showStocked") {
    $selected_value = 'selected="selected"';
    $sqlAllOrStocked = "INNER";
} else {
    $selected_value = "";
}
echo '<option ' . $selected_value . ' value="showStocked">Stocked</option>';

echo '</select>';

//if(isset($row["stocking"])){	$stockingChecked = "checked";	}
//else{$stockingChecked = "";}
echo '<input type="checkbox" name="incSales" id="incSales" value ="includeSales" >
			<label for="incSales">Sales</label>';


echo '<input type="hidden" name="dateStockCheck" value="' . $formattedDate . '"/>';
echo '<input value="submit" name="formSubmit" type="submit">';
echo '</form>';

echo "<h2 class='fontBigYellow'>REVIEW CATEGORY</h2>";

$result = $conn1->query($sql);
//$num_rows = mysqli_num_rows($result);
echo "<div class='fontSmallLight'>Number of rows : ".$result->num_rows."</div>";
echo '<form action ="stock_by_category_REVIEW2.php" method="post">';

if ($result->num_rows > 0)
{
    echo '<table width="90%" id="t01">';

    echo '<tr>
			<th>No.</th>
			
			<th>stocking</th>
			<th>Category</th>
			<th>Product</th>
			<th><div>VAT</div><div>Rate</div></th>
			<th>Cost</th>
			<th>Sell</th>
			<th><div>Barcode</div><div>Supplier Code</div></th>
			<th>Stock</th>
			<th><div>Stock</div><div>Value</div></th>
			<th><div>Last</div><div>Checked</div></th>
			
		</tr>';

    $stockValueTotal = $num = 0;

    while($row = $result->fetch_assoc())
    {
        $stock = number_format( $row['UNITS'],2,".",""); # stock

        $num += 1;
        $today = date('Y-m-d H:i:s'); // Use current date and time

        $dt = new DateTime();
        $today = $dt->format('d-M-Y');
        $dateDiff = NULL;
        $highlight = "";


        if(!$row['checkedDate'] == NULL)
        {
            $dateChecked = strtotime($row['checkedDate']);
            $dateChosen = strtotime($formattedDate);
            $dateToday = strtotime($today);

            $displayDay = date("d",$dateChecked);
            $displayMonth = date("M",$dateChecked);
            $displayYear = date("Y",$dateChecked);
            $date = date("d-M-Y",$dateChecked);

            $dateDiff = (($dateToday - $dateChecked)/86400); // 86400 = 24*60*60 = Num of secs in a day
            if($dateDiff > 30 )
            {
                $checkedButton = "class = \"checkedOldButton\"";

            }else
            {	$checkedButton = "class = \"fontMedDark\"";
            }



            if($dateChosen > $dateChecked )
            {
                $highlight = 'style="background-color: #57E592;"';

                if($stock > 0)
                {	$highlight = 'style="background-color: #E5576D;"';	}

                if(floatval($stock) < 0)
                {	$highlight = 'style="background-color: #E7C938;"';	}


            }else
            {	$highlight = "";}


            $date = date("d/m",$dateChecked);
        }else // hasn't been stock checked ever
        {
            $date = "check";

            $displayDay = "Check";
            $displayMonth = "";
            $displayYear = "";


            $checkedButton = "class = \"checkedOldButton\"";
            $highlight = 'style="background-color: #57E592;"';
            if($stock > 0)
            {	$highlight = 'style="background-color: #E5576D;"';	}

            if(floatval($stock) < 0)
            {	$highlight = 'style="background-color: #E7C938;"';	}

        }
        $VAT = 100 * $row['RATE'];
        $sell = $row['PRICESELL'] * (1+$row['RATE']);
        $buy= number_format($row['PRICEBUY'],2);
        $stockValue = $buy * $stock;
        $month5 = $row['Month5'];
        $stockValueTotal += $stockValue;
        if( $stock < 0)
        {	$beacon = "fontMedRed";}
# <td><div '.$checkedButton.'> '.$date.'</div></td>
#		<td class ="lowPri">'.str_replace("0.","",$row['RATE']).'%</td>
        echo '<tr '.$highlight.'>';
        echo'<td class="lowPri">'.$num.'</td>';


        if(isset($row["stocking"])){	$stockingChecked = "checked";	}
        else{$stockingChecked = "";}
        echo '<td><input type="checkbox" name="'.$row['CODE'].'[stocking]'.'" value ="' . $row["CODE"] . '" '.$stockingChecked.'></td>';


        echo '<td>';
        echo '<select name ="'.$row["CODE"].'[category]'.'">';
        echo '<option disabled selected value> - choose category - </option>';
        foreach($categoryArray as $catID => $catName)
        {
            if ($row['catID'] == $catID){$selected_value = 'selected="selected"';}else{$selected_value = "";}
            echo '<option '.$selected_value.' value= "' . $catID .'">' . $catName . '</option>';
        }
        echo '</select>';
        echo '</td>';


        echo'<td><div id ="'.$row['CODE'].'"><a class="stockButton" href="edit_product.php?pid='.$row['CODE'].'&page='.basename($_SERVER['PHP_SELF']).'"target="_blank">'.$row['NAME'].'</td>
		<td class ="lowPri">'.$VAT.'%</td>
		<td class="lowPri">€'.$buy.'</td>
		<td class="lowPri">€'.(number_format($sell,2)).'</td>
		<td>'.$row['CODE'].'<div class="label">'.$row['SupplierCode'].'</div></td>


		<td><a class="stockButton" href="stock_check.php?pid='.$row['CODE'].'&supplierID='.$category.'&pageName='.$pageName.'&date='.$formattedDate.'">'.floatval($stock).'</a></td>


		<td class="lowPri">'.$stockValue.'</td>
		<td ><div '.$checkedButton.'> '.$displayDay.'</div><div class="label">'.$displayMonth.' '.$displayYear.'</div></td>';

        if($incSales == "includeSales")
        { echo '<td class="lowPri">'.$row['Month5'].'</td>
				<td class="lowPri">'.$row['Month4'].'</td>
				<td class="lowPri">'.$row['Month3'].'</td>
				<td class="lowPri">'.$row['Month2'].'</td>
				<td class="lowPri">'.$row['Month1'].'</td>';

        }

        echo '</tr>';
        echo '<input type="hidden" name="'.$row['CODE'].'[barcode]'.'" value ="' . $row["CODE"] . '"/>';
    }
    echo '</table>';
    echo "Total value of stock : ".$stockValueTotal;

}
else
{
    echo "0 results";
}
echo'<input type="hidden" name="dateStockCheck" value="'.$formattedDate.'">
	<input type="hidden" name="categoryList" value="' . $category . '"/>
	<input type="hidden" name="allOrStocked" value="' . $allOrStocked . '"/>
	<input type="hidden" name="searchOrder" value="' . $searchOrder . '"/>';

echo '<input type ="submit" name ="submit" value="Save" />';

echo'</form>';


echo'<form action="stock_by_category_REVIEW2.php" method="post">';

echo'<input type="date" name="dateStockCheck" value="'.$formattedDate.'">
		<input type="hidden" name="categoryList" value="' . $category . '"/>
		<input type="hidden" name="allOrStocked" value="' . $allOrStocked . '"/>
		<input type="hidden" name="searchOrder" value="' . $searchOrder . '"/>
		<input value="change date ►" name="dateSubmit" type="submit">';
echo'<input value="Set to Zero" name="setToZero" type="submit">';

echo '</form>';



$conn1->close();

?>


</body>
</html>


