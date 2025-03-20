<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "mmhr_census";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sheets_query = "SELECT DISTINCT sheet_name FROM patient_records";
$sheets_result = $conn->query($sheets_query);
$sheets = [];
while ($row = $sheets_result->fetch_assoc()) {
    $sheets[] = $row['sheet_name'];
}

$sheets_query_2 = "SELECT DISTINCT sheet_name_2 FROM patient_records_2";
$sheets_result_2 = $conn->query($sheets_query_2);
$sheets_2 = [];
while ($row = $sheets_result_2->fetch_assoc()) {
    $sheets_2[] = $row['sheet_name_2'];
}

$sheets_query_3 = "SELECT DISTINCT sheet_name_3 FROM patient_records_3";
$sheets_result_3 = $conn->query($sheets_query_3);
$sheets_3 = [];
while ($row = $sheets_result_3->fetch_assoc()) {
    $sheets_3[] = $row['sheet_name_3'];
}

$selected_sheet_1 = $_GET['sheet_1'] ?? ($sheets[0] ?? '');
$selected_sheet_2 = $_GET['sheet_2'] ?? ($sheets[0] ?? '');
$selected_sheet_3 = $_GET['sheet_3'] ?? ($sheets[0] ?? '');

$query = "SELECT admission_date, discharge_date, member_category FROM patient_records 
          WHERE sheet_name = '$selected_sheet_1'
          AND MONTH(admission_date) != 2
          AND MONTH(discharge_date) != 2
          AND (MONTH(admission_date) = 1 OR MONTH(admission_date) = 12)";

$result = $conn->query($query);

$summary = array_fill(1, 31, [
    'govt' => 0, 'private' => 0, 'self_employed' => 0, 'ofw' => 0,
    'owwa' => 0, 'sc' => 0, 'pwd' => 0, 'indigent' => 0, 'pensioners' => 0,
    'nhip' => 0, 'non_nhip' => 0, 'total_admissions' => 0, 'total_discharges_nhip' => 0,
    'total_discharges_non_nhip' => 0,'lohs_nhip' => 0, 'lohs_non_nhip' => 0
]);

while ($row = $result->fetch_assoc()) {
    $admit = new DateTime($row['admission_date']);
    $discharge = new DateTime($row['discharge_date']);
    $category = strtolower($row['member_category']);

    $startDay = max(1, ($admit->format('m') == '12') ? 1 : (int) $admit->format('d'));
    $endDay = min(31, (int) $discharge->format('d') - 1);

    if ($startDay <= 31 && $endDay >= 1) {
        for ($day = $startDay; $day <= $endDay; $day++) {
            if (strpos($category, 'formal-government') !== false || strpos($category, 'sponsored- local govt unit') !== false) {
                $summary[$day]['govt'] += 1;
            } elseif (strpos($category, 'formal-private') !== false) {
                $summary[$day]['private'] += 1;
            } elseif (strpos($category, 'self earning individual') !== false || strpos($category, 'indirect contributor') !== false
              || strpos($category, 'informal economy- informal sector') !== false) {
                $summary[$day]['self_employed'] += 1;
            } elseif (strpos($category, 'ofw') !== false) {
                $summary[$day]['ofw'] += 1;
            } elseif (strpos($category, 'migrant worker') !== false) {
                $summary[$day]['owwa'] += 1;
            } elseif (strpos($category, 'senior citizen') !== false) {
                $summary[$day]['sc'] += 1;
            } elseif (strpos($category, 'pwd') !== false) {
                $summary[$day]['pwd'] += 1;
            } elseif (strpos($category, 'indigent') !== false || strpos($category, 'sponsored- pos financially incapable') !== false
              || strpos($category, '4ps/mcct') !== false) {
                $summary[$day]['indigent'] += 1;
            } elseif (strpos($category, 'lifetime member') !== false) {
                $summary[$day]['pensioners'] += 1;
            }
        }
    }
    
    foreach ($summary as $day => $row) {
        $summary[$day]['nhip'] = 
            $row['govt'] + $row['private'] + $row['self_employed'] + 
            $row['ofw'] + $row['owwa'] + $row['sc'] + 
            $row['pwd'] + $row['indigent'] + $row['pensioners'];
    }    

    foreach ($summary as $day => $row) {
        $summary[$day]['lohs_nhip'] = 
            $row['govt'] + $row['private'] + $row['self_employed'] + 
            $row['ofw'] + $row['owwa'] + $row['sc'] + 
            $row['pwd'] + $row['indigent'] + $row['pensioners'];
    }  
}
    $admission_query = "SELECT admission_date_2 FROM patient_records_2 WHERE sheet_name_2 = '$selected_sheet_2'";
    $admission_result = $conn->query($admission_query);

    while ($row = $admission_result->fetch_assoc()) {
        $admit_day = (int)date('d', strtotime($row['admission_date_2']));

        if ($admit_day >= 1 && $admit_day <= 31) {
            $summary[$admit_day]['total_admissions'] += 1;
        }
    }

    $discharge_query = "SELECT date_discharge, category FROM patient_records_3 WHERE sheet_name_3 = '$selected_sheet_3'";
    $discharge_result = $conn->query($discharge_query);

    while ($row = $discharge_result->fetch_assoc()) {
        $discharge_day = (int)date('d', strtotime($row['date_discharge'])); 
        $category = strtolower($row['category']);

        if ($discharge_day >= 1 && $discharge_day <= 31) {
            if (!isset($summary[$discharge_day])) {
                $summary[$discharge_day] = [
                    'total_discharges_non_nhip' => 0,
                    'total_discharges_nhip' => 0
                ];
            }

            if (strpos($category, 'non phic') !== false) {
                $summary[$discharge_day]['total_discharges_non_nhip'] += 1;
            } else {
                $summary[$discharge_day]['total_discharges_nhip'] += 1;
            }
        }
    }

    $non_nhip_query = "SELECT date_admitted, date_discharge, category FROM patient_records_3 WHERE sheet_name_3 = '$selected_sheet_3'";
    $non_nhip_result = $conn->query($non_nhip_query);

    while ($row = $non_nhip_result->fetch_assoc()) {
        $admit = new DateTime($row['date_admitted']);
        $discharge = new DateTime($row['date_discharge']);
        $category = strtolower($row['category']);

        if (strpos($category, 'non phic') !== false) {
            $startDay = max(1, (int) $admit->format('d'));
            $endDay = min(31, (int) $discharge->format('d') - 1);

            if ($startDay <= 31 && $endDay >= 1) {
                for ($day = $startDay; $day <= $endDay; $day++) {
                    $summary[$day]['non_nhip'] += 1;
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MMHR Census</title>
    <link rel="stylesheet" href="sige\summary.css">
</head>
<body class="container mt-4">
    
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">BMCI</a>
    </div>
</nav>

<aside>
    <div class="sidebar">
        <h2>Upload Excel File</h2>
        <form action="upload.php" method="POST" enctype="multipart/form-data">
            <input type="file" name="excelFile" accept=".xlsx, .xls">
            <button type="submit">Upload</button>

            <button onclick="printTable()" class="btn btn-success">Print Table</button>
        </form>
    </div>
</aside>

<div class="table-responsive">
    <h2 class="text-center mb-4">MMHR Census Summary Table</h2>
    <form action="mmhr_census.php" method="GET">
        <input type="hidden" name="sheet_1" value="<?php echo $selected_sheet_1; ?>">
        <input type="hidden" name="sheet_2" value="<?php echo $selected_sheet_2; ?>">
        <input type="hidden" name="sheet_3" value="<?php echo $selected_sheet_3; ?>">
        <button type="submit" class="btn btn-primary mt-3">View MMHR Census</button>
    </form>

    <form method="GET" class="mb-3">
        <div class="sige">
        <label class="col2-5"></label>
        <select name="sheet_1" onchange="this.form.submit()" class="form-select mb-2">
            <?php foreach ($sheets as $sheet) { ?>
                <option value="<?php echo $sheet; ?>" <?php echo $sheet === $selected_sheet_1 ? 'selected' : ''; ?>>
                    <?php echo $sheet; ?>
                </option>
            <?php } ?>
        </select>
        <label class="col7"></label>
        <select name="sheet_2" onchange="this.form.submit()" class="form-select mb-2">
        <option value="" disabled selected>Select Admission Sheet</option>
            <?php foreach ($sheets_2 as $sheet) { ?>
                <option value="<?php echo $sheet; ?>" <?php echo $sheet === $selected_sheet_2 ? 'selected' : ''; ?>>
                    <?php echo $sheet; ?>
                </option>
            <?php } ?>
        </select>
        <label class="col8"></label>
        <select name="sheet_3" onchange="this.form.submit()" class="form-select mb-2">
        <option value="" disabled selected>Select Discharge Sheet</option>
            <?php foreach ($sheets_3 as $sheet) { ?>
                <option value="<?php echo $sheet; ?>" <?php echo $sheet === $selected_sheet_3 ? 'selected' : ''; ?>>
                    <?php echo $sheet; ?>
                </option>
            <?php } ?>
        </select>
        </div>
    </form>

    <div class="table-responsive1">
        <table class="table table-bordered">
            <thead class="table-dark text-center">
            <tr>
                    <th colspan="1" style="background-color: black; color: white;">1</th>
                    <th colspan="2" style="background-color: black; color: white;">2</th>
                    <th colspan="5" style="background-color: black; color: white;">3</th>
                    <th rowspan="1" style="background-color: black; color: white;">4</th>
                    <th rowspan="1" style="background-color: black; color: white;">5</th>
                    <th colspan="2" style="background-color: black; color: white;">6</th>
                    <th rowspan="1" style="background-color: black; color: white;">7</th>
                    <th colspan="2" style="background-color: black; color: white;">8</th>
                    <th colspan="2" style="background-color: black; color: white;">9</th>
                </tr>
                <tr>
                    <th rowspan="2" style="background-color: #c7f9ff;">Date</th>
                    <th colspan="2" style="background-color: yellow;">Employed</th>
                    <th colspan="5" style="background-color: yellow;">Individual Paying</th>
                    <th rowspan="2" style="background-color: yellow;">Indigent</th>
                    <th rowspan="2" style="background-color: yellow;">Pensioners</th>
                    <th colspan="2" style="background-color: #c7f9ff;"> NHIP / NON-NHIP</th>
                    <th rowspan="2" style="background-color: yellow;">Total Admissions</th>
                    <th colspan="2" style="background-color: yellow;">Total Discharges</th>
                    <th colspan="2" style="background-color: yellow;">Accumulated Patients LOHS</th>
                </tr>
                <tr>
                    <th style="background-color: green;">Gov’t</th><th style="background-color: green;">Private</th>
                    <th style="background-color: green;">Self-Employed</th><th style="background-color: green;">OFW</th>
                    <th style="background-color: green;">OWWA</th><th style="background-color: green;">SC</th><th style="background-color: green;">PWD</th>
                    <th style="background-color:rgb(0, 0, 0); color: white;">NHIP</th><th style="background-color: #c7f9ff;">NON-NHIP</th>
                    <th style="background-color: orange;">NHIP</th><th style="background-color: orange;">NON-NHIP</th>
                    <th style="background-color: blue;">NHIP</th><th style="background-color: blue;">NON-NHIP</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                
                $totals = [
                    'govt' => 0, 'private' => 0, 'self_employed' => 0, 'ofw' => 0,
                    'owwa' => 0, 'sc' => 0, 'pwd' => 0, 'indigent' => 0, 'pensioners' => 0,
                    'nhip' => 0, 'non_nhip' => 0, 'total_admissions' => 0, 'total_discharges_nhip' => 0,
                    'total_discharges_non_nhip' => 0, 'lohs_nhip' => 0
                ];

                foreach ($summary as $day => $row) { 
                
                    foreach ($totals as $key => &$total) {
                        $total += $row[$key];
                    }
                ?>
                    <tr>
                        <td class="text-center"> <?php echo $day; ?> </td> 
                        <td class="text-center"> <?php echo $row['govt']; ?> </td>
                        <td class="text-center"> <?php echo $row['private']; ?> </td>
                        <td class="text-center"> <?php echo $row['self_employed']; ?> </td>
                        <td class="text-center"> <?php echo $row['ofw']; ?> </td>
                        <td class="text-center"> <?php echo $row['owwa']; ?> </td>
                        <td class="text-center"> <?php echo $row['sc']; ?> </td>
                        <td class="text-center"> <?php echo $row['pwd']; ?> </td>
                        <td class="text-center"> <?php echo $row['indigent']; ?> </td>
                        <td class="text-center"> <?php echo $row['pensioners']; ?> </td>
                        <td class="text-center" style="background-color: black; color: white;"> <?php echo $row['nhip']; ?> </td>
                        <td class="text-center"> <?php echo $row['non_nhip']; ?> </td>
                        <td class="text-center"> <?php echo $row['total_admissions']; ?> </td>
                        <td class="text-center"> <?php echo $row['total_discharges_nhip']; ?> </td>
                        <td class="text-center"> <?php echo $row['total_discharges_non_nhip']; ?> </td>
                        <td class="text-center"> <?php echo $row['lohs_nhip']; ?> </td>
                        <td class="text-center"> <?php echo $row['non_nhip']; ?> </td>
                    </tr>
                <?php } ?>

                <tfoot class="footer">
                <tr class="table-dark text-center fw-bold">
                    <td style="background-color:rgb(0, 0, 0); color: white;">Total</td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['govt']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['private']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['self_employed']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['ofw']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['owwa']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['sc']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['pwd']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['indigent']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['pensioners']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['nhip']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['non_nhip']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['total_admissions']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['total_discharges_nhip']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['total_discharges_non_nhip']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['lohs_nhip']; ?></td>
                    <td style="background-color:rgb(0, 0, 0); color: white;"><?php echo $totals['non_nhip']; ?></td>
                </tr>
                </tfoot>
            </tbody>
        </table>
    </div>
</div>

<script>
function printTable() {
    var printContents = document.querySelector('.table-responsive1').innerHTML;
    var originalContents = document.body.innerHTML;

    document.body.innerHTML = `
        <html>
            <head>
                <title>Print Table</title>
                <style>
                    @media print {
                        body {
                            font-family: Arial, sans-serif;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        th, td {
                            border: 1px solid black;
                            padding: 8px;
                            text-align: center;
                        }
                    }
                </style>
            </head>
            <body>
                ${printContents}
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            document.body.innerHTML = originalContents;
                        };
                    };
                </script>
            </body>
        </html>
    `;
}
</script>

</body>
</html>
