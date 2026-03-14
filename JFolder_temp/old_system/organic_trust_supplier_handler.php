<!DOCTYPE HTML>
<html>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" type="text/css" href="css/style2021.css">
	<link href="../fontawesome/css/all.css" rel="stylesheet"> <!--load all styles -->
<center>
</head>
<body>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include necessary files and establish database connection
require_once('functions.php');
require_once('/var/webconfig/config.php');
$conn1 = f_mysqlConnect(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);


// Check if year is set in POST request
if (isset($_POST['start_date']) && isset($_POST['end_date'])) {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
	echo $startDate." -> ".$endDate."  ..ver2";
    // SQL Query

            
            
		$sql = "SELECT OSAccounts.EXPENSES_JOINED.Name as FoodSupplier,
		sum(((1 + OSAccounts.TAXES.Rate) * OSAccounts.INVOICE_DETAIL.Amount)) AS TotalAmount 
		FROM OSAccounts.INVOICES
		left join OSAccounts.EXPENSES_JOINED ON OSAccounts.INVOICES.SupplierID = OSAccounts.EXPENSES_JOINED.ID
		left join OSAccounts.INVOICE_DETAIL ON OSAccounts.INVOICES.ID = OSAccounts.INVOICE_DETAIL.InvoiceID
		LEFT JOIN OSAccounts.TAXES ON OSAccounts.INVOICE_DETAIL.VatID = OSAccounts.TAXES.ID
		inner join unicenta2023_April.suppliers on OSAccounts.INVOICES.SupplierID = unicenta2023_April.suppliers.SupplierID
		where DATE_FORMAT( InvoiceDate, '%Y-%m-%d') BETWEEN ? AND ? 
		GROUP BY FoodSupplier
		ORDER BY FoodSupplier ASC";
            

    // Prepare the SQL statement
    if ($stmt = $conn1->prepare($sql)) {
        // Bind the parameters
        //$startDate = "$selectedYear 01 01";
        //$endDate = "$selectedYear 12 31";
        $stmt->bind_param("ss", $startDate, $endDate);

        // Execute the query
        $stmt->execute();


		// Fetch the results
		$stmt->bind_result($foodSupplier, $totalAmount);

		// Start the table
		echo "<table border='1'>";
		echo "<tr><th>Food Supplier</th><th>Total Amount</th></tr>";
		$totalAmountSum = 0;

		// Fetch the results
		while ($stmt->fetch()) {
			echo "<tr>";
			echo "<td>" . $foodSupplier . "</td>";
			echo "<td>" . number_format($totalAmount, 2) . "</td>";
			echo "</tr>";
			
			$totalAmountSum += $totalAmount;
		}

		echo "<tr>";
		echo "<td>TOTAL</td>";
		echo "<td>" . number_format($totalAmountSum, 2) . "</td>";
		echo "</tr>";

		// End the table
		echo "</table>";

        // Close the statement
        $stmt->close();
    } else {
        // Handle errors with preparing the statement
        echo "Error preparing statement: " . $conn1->error;
    }

    // Close the connection
    $conn1->close();
} else {
    echo "Year not specified.";
}
?>
</body>
</html>
