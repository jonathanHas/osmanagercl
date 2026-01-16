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

$conn1 = f_mysqlConnect(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);

$sql = "SELECT SupplierID, Supplier from suppliers ORDER BY Supplier ASC";
$sup_result = $conn1->query($sql);


if( $_SERVER['REQUEST_METHOD'] == 'GET')
{
	$deliveryID = $_GET['delID'];
	$supplierID = $_GET['supplierID'];
	echo "Delivery ID is ".$deliveryID;
}


if( $_SERVER['REQUEST_METHOD'] == 'POST')
{
	$supplierID = $_POST['supplierList'];
	echo "<table>";
    foreach ($_POST as $key => $value) 
    {
        echo "<tr><td>".$key."</td><td>".$value."</td></tr>";
    }
    echo "</table>";

	
}

#**************** Display delivered products, compare prices ************************####
/*
$sql = "SELECT PRODUCTS.NAME,deliveriesScanItems.barcode,sum(quantity) as scanned,supID,supplier_link.SupplierCode,caseUnits,STOCKCURRENT.UNITS
FROM deliveriesScanItems
INNER JOIN deliveriesScan ON deliveriesScanItems.delID = deliveriesScan.ID
LEFT JOIN PRODUCTS ON deliveriesScanItems.barcode = PRODUCTS.CODE
LEFT JOIN supplier_link ON deliveriesScanItems.barcode = supplier_link.Barcode AND supplier_link.SupplierID = '".$supplierID."'
LEFT JOIN STOCKCURRENT ON STOCKCURRENT.PRODUCT = PRODUCTS.ID
WHERE delID = '".$deliveryID."'
GROUP BY NAME,barcode,supID,SupplierCode,caseUnits,UNITS";
*/


$sql = "SELECT prodName,supCode,supplier_link.Barcode,rrPrice,Min(delivery.cost) as cost,PRICEBUY,PRICESELL,RATE,SUM(myOrder) as myOrder,STOCKCURRENT.UNITS, 
(PRICESELL - Min(delivery.cost)) / PRICESELL AS margin,
supplier_link.CaseUnits,delivery.caseUnits,
b.barcode,b.scanned
FROM delivery
LEFT JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode and supplier_link.SupplierID = '".$supplierID."' 
LEFT JOIN PRODUCTS ON supplier_link.Barcode = PRODUCTS.CODE
LEFT JOIN TAXES ON PRODUCTS.TAXCAT = TAXES.ID
LEFT JOIN STOCKCURRENT ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
LEFT JOIN 
		(
			SELECT deliveriesScanItems.barcode,sum(quantity) as scanned
			FROM deliveriesScanItems
			WHERE delID = '".$deliveryID."'
			GROUP BY barcode
        ) b ON b.barcode = supplier_link.Barcode
WHERE SupplierID = '".$supplierID."'
GROUP BY prodName,supCode,supplier_link.Barcode,rrPrice,PRICEBUY,PRICESELL,RATE,delivery.caseUnits,supplier_link.CaseUnits,b.barcode,b.scanned,UNITS
ORDER BY scanned desc, margin asc";

//echo "<br>".$sql."<br>";

$result = $conn1->query($sql);


echo "<br>Number of rows : ".$result->num_rows."<br>";

if ($supplierID == '5') // UDEA we need to add on delivery charge
{
	echo '<span class="fontBigYellow"><br>Profit and Margin adjusted to allow for Udea delivery charge<br></span>';
}

if ($result->num_rows > 0) 
{
	echo '<table width="90%"><table id = "t01"';
	echo '<tr>
		<th>Order</th>
		<th>Case<div>Units</div></th>
		<th><div>Old</div>Case<div>Units</div></th>
		<th>Product Name</th><th>Supplier Code</th><th>VAT</th><th>Barcode</th>
		<th>New Cost</th><th>Cost</th><th>Sell</th>
		<th>New Sell</th><th>Profit</th>
		<th>Margin</th>
		<th>Stock</th>

		<th>Invoiced</th>
		<th> </th>
		<th>Scanned</th>
		</tr>';
		
	while($row = $result->fetch_assoc()) 
	{
		$beacon="medPri";
		if( $row['PRICESELL']< $row['cost'])
		{	$beacon = "redPri";}

		$VAT = 100 * $row['RATE'];
		$sell = $row['PRICESELL'] * (1+$row['RATE']);
		
		if ($supplierID == '5') // UDEA we need to add on delivery charge
		{
			$profit = $row['PRICESELL'] - ($row['cost']*1.15);
		}
		else
		{
			$profit = $row['PRICESELL'] - $row['cost'];
		}
		$margin = $profit /  $row['PRICESELL'];

		$units_scanned = number_format( $row['scanned'],2,".","");
		$units_tailed = number_format( $row['UNITS'],2,".","");
		
		if(fmod($row['myOrder'],1) == 0.0){
			$unitsDelivered = $row['caseUnits'] * $row['myOrder'];
		}else{
			$unitsDelivered = round($row['caseUnits'] * $row['myOrder']);
		}

		if($row['caseUnits'] == $row['CaseUnits'])
		{	
			$showCaseDiff = "lowPri";}
		else
		{	$showCaseDiff = "fontMedRed";	}


		if($units_scanned == $unitsDelivered)
		{
			$highlight = 'style="background-color: #CBDDAD;"';
		}
		else if($row['scanned'] == NULL)
		{
			$highlight='';	
		}
		else
		{
			$highlight = 'style="background-color: #D08992;"';
		}
		
						
		echo '<tr '.$highlight.'>
		<td class="lowPri">'.$row['myOrder'].'</td>
		<td class="'.$showCaseDiff.'">'.$row['caseUnits'].'</td>
		<td class="'.$showCaseDiff.'">'.$row['CaseUnits'].'</td>
		<td class="fontMedDark"><div id ="'.$row['Barcode'].'"><a href="edit_product.php?pid='.$row['Barcode'].'&page='.basename($_SERVER['PHP_SELF']).'&supplierID='.$supplierID.'">'.$row['prodName'].'</a></td>';
		echo '<td>'.$row['supCode'].'</td>
		<td class="lowPri">'.$VAT.'%</td>
		<td class="lowPri">'.$row['Barcode'].'</td>


		<td class="lowPri">'.$row['cost'].'</td>

		<td class="'.$beacon.'">'.(number_format($row['PRICEBUY'],2)).'</td>
		<td class="'.$beacon.'">'.(number_format($sell,2)).'</td>
		
		<td class="lowPri">'.$row['rrPrice'].'</td>		

		<td class="lowPri">€'.(number_format($profit,2)).'</td>
		<td class="lowPri">'.(100*(number_format($margin,2))).'%</td>
		<td class="lowPri">'.floatval($units_tailed).'</td>

		<td class="fontMedDark">'.$unitsDelivered.'</td>
		<td> </td>
		<td class="fontMedBlue">'.$row['scanned'].'</td>';
		echo'</tr>';
	}
	
	/**************Bottom row of table -- Update Case Units & Cost Price*************************************/

	
	echo '</table>';

//	echo '<div><br><a class="deleteButton" href="delivery_scan_update_stock.php?delID='.$deliveryID.'&supplierID='.$supplierID.'">Update Stock</a><br>.</div>';

/*******************Run a query to see what is scanned but not matching the invoice****************************************************************************/

	echo "<div>Items scanned but not matching invoice</div>";
	
	$sql = "SELECT deliveriesScanItems.barcode as Barcode,sum(quantity) as scanned, PRODUCTS.NAME
		FROM deliveriesScanItems
		LEFT JOIN PRODUCTS ON PRODUCTS.CODE = deliveriesScanItems.barcode
		WHERE delID = '".$deliveryID."'
		AND Barcode NOT IN
		(
			SELECT supplier_link.Barcode
				FROM delivery
				LEFT JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode and supplier_link.SupplierID = '".$supplierID."' 
				LEFT JOIN PRODUCTS ON supplier_link.Barcode = PRODUCTS.CODE
				LEFT JOIN 
						(
							SELECT deliveriesScanItems.barcode,sum(quantity) as scanned
							FROM deliveriesScanItems
							WHERE delID = '".$deliveryID."'
							GROUP BY barcode
						) b ON b.barcode = supplier_link.Barcode
				WHERE SupplierID = '".$supplierID."'
				GROUP BY prodName,supCode,supplier_link.Barcode
				ORDER BY supCode
		)
		GROUP BY Barcode
		ORDER BY PRODUCTS.NAME
		";	
	
	$result = $conn1->query($sql);

	echo "Number of rows : ".$result->num_rows."<br>";
	if ($result->num_rows > 0) 
	{
		echo '<table width="90%"><table id = "t01"';
		echo '<tr>
			<th>Barcode</th>
			<th>Scanned</th>
			<th>Name</th>
			</tr>';
			
		while($row = $result->fetch_assoc()) 
		{
			echo '<tr>
			<td class="lowPri">'.$row['Barcode'].'</td>
			<td class="lowPri">'.$row['scanned'].'</td>
			<td class="lowPri">'.$row['NAME'].'</td>';
			echo'</tr>';
		}
		echo '</table>';
	
	}
	


/*******************Run a query to see what is on the invoice but not scanned****************************************************************************/

	
	echo "<div>Invoiced but not scanned (probably supplier code doesnt match a barcode)</div>";
	$sql = "SELECT supCode, prodName, deliveredUnits FROM unicenta2016.delivery
		WHERE supCode NOT IN
		(
		SELECT supCode
			FROM delivery
			LEFT JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode and supplier_link.SupplierID = '".$supplierID."'
			LEFT JOIN PRODUCTS ON supplier_link.Barcode = PRODUCTS.CODE
			LEFT JOIN 
					(
						SELECT deliveriesScanItems.barcode,sum(quantity) as scanned
						FROM deliveriesScanItems
						WHERE delID = '".$deliveryID."'
						GROUP BY barcode
					) b ON b.barcode = supplier_link.Barcode
			WHERE SupplierID = '".$supplierID."'
			GROUP BY prodName,supCode,supplier_link.Barcode
			
		)
		ORDER BY prodName ASC
		
		";	
	
	$result = $conn1->query($sql);

	echo "Number of rows : ".$result->num_rows."<br>";
	if ($result->num_rows > 0) 
	{
		echo '<table width="90%"><table id = "t01"';
		echo '<tr>
			<th>Supplier Code</th>
			<th>Name</th>
			<th>Invoiced</th>
			</tr>';
			
		while($row = $result->fetch_assoc()) 
		{
			echo '<tr>
			<td class="lowPri">'.$row['supCode'].'</td>
			<td class="lowPri">'.$row['prodName'].'</td>
			<td class="lowPri">'.$row['deliveredUnits'].'</td>';
			echo'</tr>';
		}
		echo '</table>';
	
	}
	


}
else 
{
	echo "0 results";
}



$conn1->close();

?>
