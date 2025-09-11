<!DOCTYPE HTML>
<html>
<head>
	<meta charset ="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" type="text/css" href="css/styleGridLodgement.css">
	<title>Lodgement Form</title>
<center>
</head>
<body>
	
<?php
    include 'includes/header.php';
require_once 'functions.php';
require_once '/var/webconfig/config.php';

$conn1 = f_mysqlConnect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

$formattedDate1 = date('Y-m-d');
$dateChosen = false;
$cash50 = $cash20 = $cash10 = $cash5 = $cash2 = $cash1 = $cash50c = $cash20c = $cash10c = $cheque = '0';
$card = $cashBack = $coinFloat = $noteFloat = $debt = $debtPaidCash = $debtPaidCheque = $debtPaidCard = $free = $voucherUsed = $moneyAdded = '0';

$closedCashAr = []; // holds the cash counted from the till in 50s, 20s, 10s etc..
$countAr = []; // holds the cash counted to lodge temporarily until the total is inserted into the lodgement table

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo '<br><br><br>hi';
    echo '<table>';
    foreach ($_POST as $key => $value) {
        echo '<tr><td>'.$key.'</td><td>'.$value.'</td></tr>';
        if (is_array($value)) {
            echo '<tr><td>Its an array</td></tr>';
            foreach ($value as $inKey => $inValue) {
                echo '<tr><td>'.$inKey.'</td><td>'.$inValue.'</td></tr>';
                foreach ($inValue as $finKey => $finValue) {
                    echo '<tr><td>'.$finKey.'</td><td>'.$finValue.'</td></tr>';
                }
            }
        }
    }
    echo '</table>';

    if (isset($_POST['date'])) {
        $date = date('Y m d', strtotime(f_CleanAndTrim($conn1, $_POST['date'])));
        $formattedDate1 = date('Y-m-d', strtotime($_POST['date']));
        $dateChosen = true;

        /*****************************************************************************************/
        // Find out which tills were closed on selected date
        // so we can create a table that can grow or shrink depending
        // on how many tills were used
        $sql = "SELECT CLOSEDCASH.HOST
			FROM PAYMENTS INNER JOIN RECEIPTS ON PAYMENTS.RECEIPT = RECEIPTS.ID 
			INNER JOIN CLOSEDCASH ON RECEIPTS.MONEY = CLOSEDCASH.MONEY 
			WHERE DATE_FORMAT( DATEEND, '%Y %m %d' ) = '".$date."'
			group by HOST";

        $till_result = $conn1->query($sql);
        $tillNameAr = [];
        $closedTillsTotalsAr = [];

        $i = 0;
        while ($till_row = $till_result->fetch_assoc()) {
            $tillNameAr[$i] = $till_row['HOST'];
            $closedTillsTotalsAr[$till_row['HOST']] = 0;
            $i++;
        }

        $sqlGen = '';
        for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {
            // echo "Host : ".$tillNameAr[$x]."<br>";
            if ($x == (count($tillNameAr) - 1)) {
                $sqlGen .= "SUM( IF(HOST='".$tillNameAr[$x]."',TOTAL,0)) AS '".$tillNameAr[$x]."'";
            } else {
                $sqlGen .= "SUM( IF(HOST='".$tillNameAr[$x]."',TOTAL,0)) AS '".$tillNameAr[$x]."',";
            }
        }
        /*****************************************************************************************/

        // select MONEY (id to connect to closed cash) and HOST (till name)
        // need to load results into array for each till
        $sql = "SELECT MONEY, HOST FROM unicenta2016.CLOSEDCASH WHERE DATE_FORMAT( DATEEND, '%Y %m %d' ) = '".$date."'";

        $result = $conn1->query($sql); // {echo "<br><h3>Found!</h3><br>"; }	else{ echo "Not Found";}

        while ($row = $result->fetch_assoc()) {

            echo '<br>'.$row['MONEY'];
            echo '<br>Host : '.$row['HOST'].'<br>';

            $closedCashAr[$row['HOST']]['moneyid'] = $row['MONEY'];

            // Check if we have a lodgment already with the same money ID
            $sql = "SELECT cash FROM unicenta2016.lodgeCnt WHERE moneyID = '".$row['MONEY']."'";
            echo '<br>'.$sql;
            if ($result2 = $conn1->query($sql)) {
                $row2 = $result2->fetch_assoc();

                $closedCashAr[$row['HOST']]['lodgeAmt'] = $row2['cash'];
                echo '<br>HI repeat '.$closedCashAr[$row['HOST']]['lodgeAmt'];
                echo '<br>Lodgement '.$row['HOST'].' '.$closedCashAr[$row['HOST']]['lodgeAmt'].'<br>';
            } else {

            }

            echo 'Not Found';
            // $closedCashAr[$row['HOST']][lodgeAmt] = 0;

            $sql = "SELECT ID, cash50,cash20,cash10,cash5,cash2,cash1,cash50c,cash20c,cash10c,cheque,card,cashBack,coinFloat,noteFloat,
			debt,debtPaidCard,debtPaidCheque,debtPaidCash,free,voucherUsed,moneyAdded
			FROM money WHERE ID = '".$row['MONEY']."'";
            $result2 = $conn1->query($sql);
            if ($result2->num_rows > 0) {
                // echo "<br>Already entered<br>";
                $row2 = $result2->fetch_assoc();
                // echo "cash50 : ".$row2[cash50];
                $closedCashAr[$row['HOST']]['cash50'] = f_NumberOrZero($row2['cash50']);
                $closedCashAr[$row['HOST']]['cash20'] = f_NumberOrZero($row2['cash20']);
                $closedCashAr[$row['HOST']]['cash10'] = f_NumberOrZero($row2['cash10']);
                $closedCashAr[$row['HOST']]['cash5'] = f_NumberOrZero($row2['cash5']);
                $closedCashAr[$row['HOST']]['cash2'] = f_NumberOrZero($row2['cash2']);
                $closedCashAr[$row['HOST']]['cash1'] = f_NumberOrZero($row2['cash1']);
                $closedCashAr[$row['HOST']]['cash50c'] = f_NumberOrZero($row2['cash50c']);
                $closedCashAr[$row['HOST']]['cash20c'] = f_NumberOrZero($row2['cash20c']);
                $closedCashAr[$row['HOST']]['cash10c'] = f_NumberOrZero($row2['cash10c']);
                $closedCashAr[$row['HOST']]['cheque'] = f_NumberOrZero($row2['cheque']);
                $closedCashAr[$row['HOST']]['card'] = f_NumberOrZero($row2['card']);
                $closedCashAr[$row['HOST']]['cashBack'] = f_NumberOrZero($row2['cashBack']);
                $closedCashAr[$row['HOST']]['noteFloat'] = f_NumberOrZero($row2['noteFloat']);
                $closedCashAr[$row['HOST']]['coinFloat'] = f_NumberOrZero($row2['coinFloat']);
                $closedCashAr[$row['HOST']]['debt'] = f_NumberOrZero($row2['debt']);
                $closedCashAr[$row['HOST']]['debtPaidCard'] = f_NumberOrZero($row2['debtPaidCard']);
                $closedCashAr[$row['HOST']]['debtPaidCash'] = f_NumberOrZero($row2['debtPaidCash']);
                $closedCashAr[$row['HOST']]['debtPaidCheque'] = f_NumberOrZero($row2['debtPaidCheque']);
                $closedCashAr[$row['HOST']]['voucherUsed'] = f_NumberOrZero($row2['voucherUsed']);
                $closedCashAr[$row['HOST']]['moneyAdded'] = f_NumberOrZero($row2['moneyAdded']);
                $closedCashAr[$row['HOST']]['free'] = f_NumberOrZero($row2['free']);

            } else {
                // echo "<br>No entry<br>";
            }

        }
        // var_dump($closedCashAr);
        // echo "<br><br>";
        echo '<pre>';
        print_r($closedCashAr);
        echo '</pre>';
    }

    /***************************************************MONEY COUNT ******************************************************************************************************/
    if (isset($_POST['saveCount'])) {
        // need to perform the following on each till
        for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {
            $tillName = str_replace(' ', '_', $tillNameAr[$x]);
            if ((isset($_POST[$tillName.'MoneyID']))) {
                $closeCashID = f_CleanAndTrim($conn1, $_POST[$tillName.'MoneyID']);
                echo '<br>Matching moneyID';
                echo ' Close Cash ID = '.$closeCashID.'<br> '.$tillName.'MoneyID';
                // echo "Should have something ...".$_POST[$tillNameAr[$x].'MoneyID'];
            } else {
                echo '<br>Not mathcing moneyID<h3>'.$tillName.'</h3>';
                // echo "<br><br>";
            }

            // $tempStr = $tillNameAr[$x].'50s';
            // echo "<br> Hello ".$tempStr." ".$_POST[$tillNameAr[$x].'50s']."<br>";
            if ((isset($_POST[$tillName.'50s'])) && (is_numeric($_POST[$tillName.'50s']))) {
                $countAr[$tillName]['50s'] = f_CleanAndTrim($conn1, $_POST[$tillName.'50s']);
            }
            if ((isset($_POST[$tillName.'20s'])) && (is_numeric($_POST[$tillName.'20s']))) {
                $countAr[$tillName]['20s'] = f_CleanAndTrim($conn1, $_POST[$tillName.'20s']);
            }
            if ((isset($_POST[$tillName.'10s'])) && (is_numeric($_POST[$tillName.'10s']))) {
                $countAr[$tillName]['10s'] = f_CleanAndTrim($conn1, $_POST[$tillName.'10s']);
            }
            if ((isset($_POST[$tillName.'5s'])) && (is_numeric($_POST[$tillName.'5s']))) {
                $countAr[$tillName]['5s'] = f_CleanAndTrim($conn1, $_POST[$tillName.'5s']);
            }
            if ((isset($_POST[$tillName.'2s'])) && (is_numeric($_POST[$tillName.'2s']))) {
                $countAr[$tillName]['2s'] = f_CleanAndTrim($conn1, $_POST[$tillName.'2s']);
            }
            if ((isset($_POST[$tillName.'1s'])) && (is_numeric($_POST[$tillName.'1s']))) {
                $countAr[$tillName]['1s'] = f_CleanAndTrim($conn1, $_POST[$tillName.'1s']);
            }
            if ((isset($_POST[$tillName.'50c'])) && (is_numeric($_POST[$tillName.'50c']))) {
                $countAr[$tillName]['50c'] = f_CleanAndTrim($conn1, $_POST[$tillName.'50c']);
            }
            if ((isset($_POST[$tillName.'20c'])) && (is_numeric($_POST[$tillName.'20c']))) {
                $countAr[$tillName]['20c'] = f_CleanAndTrim($conn1, $_POST[$tillName.'20c']);
            }
            if ((isset($_POST[$tillName.'10c'])) && (is_numeric($_POST[$tillName.'10c']))) {
                $countAr[$tillName]['10c'] = f_CleanAndTrim($conn1, $_POST[$tillName.'10c']);
            }
            if ((isset($_POST[$tillName.'cheque'])) && (is_numeric($_POST[$tillName.'cheque']))) {
                $countAr[$tillName]['cheque'] = f_CleanAndTrim($conn1, $_POST[$tillName.'cheque']);
            }
            // if((isset($_POST['20s'])) && (is_numeric($_POST['20s']))){	$count20 = f_CleanAndTrim($conn1,$_POST['20s']);}else{$count20=0;}

            $totalToLodge =
            $countAr[$tillName]['50s']
            + $countAr[$tillName]['20s']
            + $countAr[$tillName]['10s']
            + $countAr[$tillName]['5s']
            + $countAr[$tillName]['2s']
            + $countAr[$tillName]['1s']
            + $countAr[$tillName]['50c']
            + $countAr[$tillName]['20c']
            + $countAr[$tillName]['10c'];

            $chequeLodge = $countAr[$tillName]['cheque'];
            /*
                        //ECHO "<br> total to lodge : ".$totalToLodge;
                        if(is_numeric($totalToLodge) && ($totalToLodge >0))
                        {
                            ECHO "<br> total to lodge ".$totalToLodge." is numeric.<br>";
                        }
                        else
                        {
                            ECHO "<br> NOT Numeric ".$totalToLodge."<br>";
                        }
            */

            if (($_POST['saveCount'] == 'Lodge') && (is_numeric($totalToLodge)) && ($totalToLodge > 0)) {
                $closedCashAr[$tillNameAr[$x]][lodgeAmt] += $totalToLodge;

                echo '<br> lodgement : '.$lodgeAmt;
                echo '<br> Already lodged : '.$closedCashAr[$tillNameAr[$x]][lodgeAmt];
                // $lodgeAmt += $totalToLodge;
                // insert into lodgeCnt
                $sql = "INSERT INTO lodgeCnt(moneyID,lDate,cash)
				VALUES ('".$closeCashID."',NOW(),'".$closedCashAr[$tillNameAr[$x]][lodgeAmt]."')
				ON DUPLICATE KEY UPDATE 
				cash='".$closedCashAr[$tillNameAr[$x]][lodgeAmt]."'";

                echo '<br>'.$sql.'<br>';

                if ($result = $conn1->query($sql)) {
                    echo '<br><h3>Count Added!</h3><br>';
                    $countAr[$tillName]['50s']
                    = $countAr[$tillName]['20s']
                    = $countAr[$tillName]['10s']
                    = $countAr[$tillName]['5s']
                    = $countAr[$tillName]['2s']
                    = $countAr[$tillName]['1s']
                    = $countAr[$tillName]['50c']
                    = $countAr[$tillName]['20c']
                    = $countAr[$tillName]['10c']
                    = '';

                } else {
                    echo 'Error in INSERT statement :'.$sql;
                }
            }

            if (($_POST['saveCount'] == 'Lodge') && (is_numeric($chequeLodge)) && ($chequeLodge > 0)) {
                // $closedCashAr[$tillNameAr[$x]][lodgeAmt] += $totalToLodge;

                echo '<br> Cheque lodgement : '.$chequeLodge;
                // echo "<br> Cheque already lodged : ".$closedCashAr[$tillNameAr[$x]][lodgeAmt];
                // $lodgeAmt += $totalToLodge;
                // insert into lodgeCnt
                $sql = "INSERT INTO lodgeCntCheques(moneyID,lDate,cheque)
				VALUES ('".$closeCashID."',NOW(),'".$chequeLodge."')
				ON DUPLICATE KEY UPDATE 
				cheque='".$chequeLodge."'";

                echo '<br>'.$sql.'<br>';

                if ($result = $conn1->query($sql)) {
                    echo '<br><h3>Cheque Added!</h3><br>';
                    $countAr[$tillName]['cheque'] = '';

                } else {
                    echo 'Error in INSERT statement :'.$sql;
                }
            }

            if ((isset($_POST['uniCash'])) && (is_numeric($_POST['uniCash']))) {
                $uniCashAmnt = f_CleanAndTrim($conn1, $_POST['uniCash']);
            }
        }
    }

}

echo '<div class="site">';

echo '<div class="masthead">';
echo '<form action="lodgement_form3.php" method="post">';
echo '<input type="date" name="date" value="'.$formattedDate1.'">
		<input value="change date ►" name="dateSubmit" type="submit">';

echo '</form>';
echo '</div>';

$sql = '
SELECT PAYMENT,
	'.$sqlGen."
	FROM PAYMENTS 
    INNER JOIN RECEIPTS ON PAYMENTS.RECEIPT = RECEIPTS.ID 
	INNER JOIN CLOSEDCASH ON RECEIPTS.MONEY = CLOSEDCASH.MONEY 
    	WHERE DATE_FORMAT( DATEEND, '%Y %m %d' ) = '".$date."'
	GROUP BY PAYMENT
";

// echo "<br>".$sql."<br>";

$result = $conn1->query($sql);
// echo "<br>Num of rows :".$result->num_rows."<br>";

if ($result->num_rows > 0) {
    echo '<div class="colophon">';
    echo '<table width="90%"><table id = "t01"';
    echo '<tr>
					<th scope="col">Payment Method</th>';
    for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {
        echo '<th scope="col">'.$tillNameAr[$x].'</th>';
    }
    echo '</tr>';

    $Total = 0;
    $DailyTotal = 0;

    /*
        for ($x = 0; $x <= (sizeof($tillNameAr)-1); $x++)
        {
            //find previous date
            $sql = "SELECT MONEY,noteFloat,coinFloat, DATE_FORMAT(DATEEND,'%Y %m %d') as date FROM CLOSEDCASH
                    left join money on CLOSEDCASH.MONEY = money.ID
                    WHERE DATE_FORMAT(DATEEND,'%Y %m %d') =
                    (	select MAX(DATE_FORMAT(DATEEND,'%Y %m %d')) from CLOSEDCASH WHERE DATE_FORMAT(DATEEND,'%Y %m %d') < '".$date."'
                        AND CLOSEDCASH.HOST = '".$tillNameAr[$x]."'	)AND CLOSEDCASH.HOST = '".$tillNameAr[$x]."'";
            echo "<br>".$sql;
            $result = $conn1->query($sql);
            if ($result->num_rows > 0)
            {
                //echo "<br>PREVIOUS DATE<br>";
                $row = $result->fetch_assoc();
                //echo "cash50 : ".$row[cash50];
                $prevDate = $row[date];
                $prevID = $row[MONEY];
                $prevNoteFloat = $row[noteFloat];
                $prevCoinFloat = $row[coinFloat];

                //echo "<br>prev ...".$prevNoteFloat;
            }
        }
    */

    //
    while ($row = $result->fetch_assoc()) {
        for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {
            // echo "<td>€".$row[$tillNameAr[$x]]."</td>";
            $closedTillsTotalsAr[$tillNameAr[$x]] += number_format($row[$tillNameAr[$x]], 2, '.', '');

        }

        echo '<tr>
						
						<td>'.$row['PAYMENT'].'</td>';
        $totalRow = 0;
        for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {
            echo '<td>€'.number_format($row[$tillNameAr[$x]], 2, '.', '').'</td>';
            $totalRow += $row[$tillNameAr[$x]];
        }
        echo '<td>€'.number_format($totalRow, 2, '.', '').'</td>';
        echo '</tr>';
        // echo '<tr><td>'.$closeCashID.'</td></tr>';
    }

    echo '<tr>
					<td class = "tDiffDark">Total</td>';
    $totalTurnover = 0;
    for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {
        // echo "<td>€".$row[$tillNameAr[$x]]."</td>";
        echo '<td>€'.number_format($closedTillsTotalsAr[$tillNameAr[$x]], 2, '.', '').'</td>';
        $totalTurnover += $closedTillsTotalsAr[$tillNameAr[$x]];

    }
    echo '<td>€'.number_format($totalTurnover, 2, '.', '').'</td>';
    echo '</tr>';

    echo '</table>';

    echo '<form action ="lodgement_form3.php" method="post">';
    echo '</div>';
} else {
    echo '<div class ="masthead"><br><h1>Till was not closed on that date, choose another date.</h1></div>';
    $dateChosen = false;
}

if ($dateChosen) {

    // ************************************************************INPUTS AND TILL COUNT************************************/
    for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {

        $grid = 'grid'.($x + 1);
        echo '<div class="'.$grid.'">';

        echo '<h3>'.$tillNameAr[$x].'</h3>';
        // echo "<td>€".number_format($closedTillsTotalsAr[$tillNameAr[$x]],2,'.','')."</td>";
        $tillName = str_replace(' ', '_', $tillNameAr[$x]);

        $ccTotal = $closedCashAr[$tillNameAr[$x]][cash50]
        + $closedCashAr[$tillNameAr[$x]][cash20]
        + $closedCashAr[$tillNameAr[$x]][cash10]
        + $closedCashAr[$tillNameAr[$x]][cash5]
        + $closedCashAr[$tillNameAr[$x]][cash2]
        + $closedCashAr[$tillNameAr[$x]][cash1]
        + $closedCashAr[$tillNameAr[$x]][cash50c]
        + $closedCashAr[$tillNameAr[$x]][cash20c]
        + $closedCashAr[$tillNameAr[$x]][cash10c];

        $countTotal = $countAr[$tillName]['50s']
        + $countAr[$tillName]['20s']
        + $countAr[$tillName]['10s']
        + $countAr[$tillName]['5s']
        + $countAr[$tillName]['2s']
        + $countAr[$tillName]['1s']
        + $countAr[$tillName]['50c']
        + $countAr[$tillName]['20c']
        + $countAr[$tillName]['10c'];

        $ccFloat = $closedCashAr[$tillNameAr[$x]][noteFloat] + $closedCashAr[$tillNameAr[$x]][coinFloat];

        echo '<table>';
        echo '<tr><th></th><th>Lodge</th><th>Till Count</th></tr>';
        echo '<tr><td><label>€50</label></td><td><input type="text" id="'.$tillName.'50s" name="'.$tillName.'50s" value ="'.$countAr[$tillName]['50s'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash50].'</td></tr>';
        echo '<tr><td><label>€20</label></td><td><input type="text" id="'.$tillName.'20s" name="'.$tillName.'20s" value ="'.$countAr[$tillName]['20s'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash20].'</td></tr>';
        echo '<tr><td><label>€10</label></td><td><input type="text" id="'.$tillName.'10s" name="'.$tillName.'10s" value ="'.$countAr[$tillName]['10s'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash10].'</td></tr>';
        echo '<tr><td><label>€5</label></td><td><input type="text" id="'.$tillName.'5s" name="'.$tillName.'5s" value ="'.$countAr[$tillName]['5s'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash5].'</td></tr>';
        echo '<tr><td><label>€2</label></td><td><input type="text" id="'.$tillName.'2s" name="'.$tillName.'2s" value ="'.$countAr[$tillName]['2s'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash2].'</td></tr>';
        echo '<tr><td><label>€1</label></td><td><input type="text" id="'.$tillName.'1s" name="'.$tillName.'1s" value ="'.$countAr[$tillName]['1s'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash1].'</td></tr>';
        echo '<tr><td><label>€50c</label></td><td><input type="text" id="'.$tillName.'50c" name="'.$tillName.'50c" value ="'.$countAr[$tillName]['50c'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash50c].'</td></tr>';
        echo '<tr><td><label>€20c</label></td><td><input type="text" id="'.$tillName.'20c" name="'.$tillName.'20c" value ="'.$countAr[$tillName]['20c'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash20c].'</td></tr>';
        echo '<tr><td><label>€10c</label></td><td><input type="text" id="'.$tillName.'10c" name="'.$tillName.'10c" value ="'.$countAr[$tillName]['10c'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cash10c].'</td></tr>';
        echo '<tr><td><label>Cheque</label></td><td><input type="text" id="'.$tillName.'cheque" name="'.$tillName.'cheque" value ="'.$countAr[$tillName]['cheque'].'" size="5""/></td><td>'.$closedCashAr[$tillNameAr[$x]][cheque].'</td></tr>';
        echo '<tr><td><label>Total</label></td><td>'.$countTotal.'</td><td>'.$ccTotal.'</td></tr>';

        echo '<tr><td><label>Float</label></td><td></td><td>'.$ccFloat.'</td></tr>';
        echo '<tr><td><label>lodged</label></td><td>'.$closedCashAr[$tillNameAr[$x]][lodgeAmt].'</td><td>'.($ccTotal - $ccFloat).'</td></tr>';

        echo '</table>';
        echo '</div>';
    }

    // ***************************************TOTALS*********************************************************************/

    echo '<footer class="unicenta-grid">';
    echo '<table class="tblBorder">
		<tr><th>description</th><th>Till</th><th>Unicenta</th><th>Difference</th></tr>
	</table>';

    for ($x = 0; $x <= (count($tillNameAr) - 1); $x++) {
        echo '	<input type="hidden" name="'.$tillNameAr[$x].'MoneyID" value="'.$closedCashAr[$tillNameAr[$x]][moneyid].'"/>';
    }
    echo '	<input type="hidden" name="date" value="'.$formattedDate1.'"/>';

    echo '<center><input type ="submit" name ="saveCount" value="Save" />';
    echo '<input type ="submit" name ="saveCount" value="Lodge" /></center>';
    echo '</footer>';
    echo '</form>';
    echo '</div>';

    $prevDate = $row[date];
    $prevID = $row[MONEY];
    $prevNoteFloat = $row[noteFloat];
    $prevCoinFloat = $row[coinFloat];
}

echo '</div>';

$conn1->close();

?>
