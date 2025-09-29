<?php
require_once 'auth_check.php';
require_once 'db_connect.php';
include 'navbar.php';

$user_id = $_SESSION['user_id'];

// Get user's books
$stmt = $conn->prepare("SELECT id, name FROM books WHERE user_id=? ORDER BY name");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user's preferred default book
$default_book_id = $_SESSION['default_book_id'] ?? ($books[0]['id'] ?? 0);
if (isset($_POST['default_book_id'])) {
    $default_book_id = intval($_POST['default_book_id']);
    $_SESSION['default_book_id'] = $default_book_id;
}

// Helper: find selected book name
$selected_book_name = '';
foreach($books as $b) {
    if ($b['id'] == $default_book_id) {
        $selected_book_name = $b['name'];
        break;
    }
}

// DASHBOARD METRICS (Current Book)
$stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN type='cashin' THEN amount ELSE 0 END) as total_in,
    SUM(CASE WHEN type='cashout' THEN amount ELSE 0 END) as total_out,
    COUNT(*) as total_trans,
    SUM(CASE WHEN type='cashin' THEN amount ELSE 0 END) - SUM(CASE WHEN type='cashout' THEN amount ELSE 0 END) as net_balance
    FROM transactions WHERE book_id=? AND created_by=?");
$stmt->bind_param("ii", $default_book_id, $user_id);
$stmt->execute();
$stmt->bind_result($total_in, $total_out, $total_trans, $net_balance);
$stmt->fetch();
$stmt->close();

// Cash In, Cash Out for current month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN type='cashin' THEN amount ELSE 0 END) as month_in,
    SUM(CASE WHEN type='cashout' THEN amount ELSE 0 END) as month_out,
    COUNT(*) as month_trans
    FROM transactions 
    WHERE book_id=? AND created_by=? AND DATE(date) BETWEEN ? AND ?");
$stmt->bind_param("iiss", $default_book_id, $user_id, $month_start, $month_end);
$stmt->execute();
$stmt->bind_result($month_in, $month_out, $month_trans);
$stmt->fetch();
$stmt->close();

// --- Cash Flow for Current Month (by day) ---
$days = [];
$cashins = [];
$cashouts = [];
$last_day = date('t');
for($i=1;$i<=$last_day;$i++) {
    $date = date('Y-m-').str_pad($i,2,'0',STR_PAD_LEFT);
    $days[] = date('M j', strtotime($date));
    $stmt = $conn->prepare("SELECT 
        SUM(CASE WHEN type='cashin' THEN amount ELSE 0 END) AS cashin, 
        SUM(CASE WHEN type='cashout' THEN amount ELSE 0 END) AS cashout 
        FROM transactions 
        WHERE created_by=? AND book_id=? AND DATE(date)=?
    ");
    $stmt->bind_param("iis", $user_id, $default_book_id, $date);
    $stmt->execute();
    $stmt->bind_result($in, $out);
    $stmt->fetch();
    $cashins[] = $in ? $in : 0;
    $cashouts[] = $out ? $out : 0;
    $stmt->close();
}

$cashflow_labels = json_encode($days);
$cashflow_in = json_encode($cashins);
$cashflow_out = json_encode($cashouts);

// --- Expense Breakdown ---
$cat_labels = [];
$cat_data = [];
$stmt = $conn->prepare("SELECT c.name, SUM(t.amount) as total 
    FROM transactions t 
    LEFT JOIN categories c ON t.category_id = c.id 
    WHERE t.book_id=? AND t.created_by=? AND t.type='cashout' AND MONTH(t.date)=MONTH(CURRENT_DATE()) AND YEAR(t.date)=YEAR(CURRENT_DATE())
    GROUP BY c.name");
$stmt->bind_param("ii", $default_book_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $cat_labels[] = $row['name'] ? $row['name'] : 'Uncategorized';
    $cat_data[] = floatval($row['total']);
}
$stmt->close();
if(empty($cat_labels)) {
    $cat_labels = ['No Expenses'];
    $cat_data = [1];
}

// --- End PHP ---
?>

<!DOCTYPE html>
<html>
<head>
    <title>DigiCashBook Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
        body { background: #f7f8fa; }
        .dashboard-cards { display: flex; gap: 18px; margin-bottom: 27px; flex-wrap: wrap;}
        .dashboard-card {
            background: #fff; border-radius: 14px; box-shadow: 0 2px 12px #0001;
            padding: 20px 19px 17px 20px; flex: 1 1 240px; min-width: 220px; 
            display: flex; flex-direction: column; justify-content: center; align-items: flex-start;
            max-width: 330px;
        }
        .dashboard-card .card-title { font-size: 1.12rem; color: #666; font-weight: 500; margin-bottom: 8px; }
        .dashboard-card .card-amount { font-size: 1.7rem; font-weight: bold; }
        .dashboard-card .card-amount.net { color: #2774ee; font-size: 1.7rem;}
        .dashboard-card .card-amount.in { color: #25ac36; font-size: 1.32rem;}
        .dashboard-card .card-amount.out { color: #df3434; font-size: 1.32rem;}
        .dashboard-card .card-amount.count { color: #222; font-size: 1.2rem;}
        .dashboard-card .icon { font-size: 1.3rem; opacity: .95; margin-right: 10px;}
        .dashboard-card .arrow-in { color: #25ac36; }
        .dashboard-card .arrow-out { color: #df3434; }
        .dashboard-card .icon.tx { color: #666; }

        /* Responsive adjustments */
        @media (max-width: 1000px) {
            .dashboard-cards { flex-wrap: wrap; gap: 12px;}
            .dashboard-card { min-width: 46vw; max-width: 100vw; flex:1 1 100px; }
        }
        @media (max-width: 600px) {
            .dashboard-cards { flex-direction: column; gap: 11px; }
            .dashboard-card { min-width: 92vw; max-width: 100vw;}
        }

        .dashboard-actions { display: flex; gap: 16px; justify-content: center; margin-bottom: 32px; flex-wrap: wrap; }
        .dashboard-actions .btn { min-width: 160px; }
        .dashboard-section { display: flex; gap: 16px; flex-wrap: wrap;}
        .dashboard-chart-card, .dashboard-pie-card {
            background: #fff; border-radius: 14px; box-shadow: 0 2px 12px #0001; padding: 17px 16px 15px 16px;
        }
        .dashboard-chart-card { flex: 2 1 420px; min-width: 320px; }
        .dashboard-pie-card { flex: 1 1 320px; min-width: 280px; }
        .dashboard-bottom-row { display: flex; gap: 16px; margin-top: 16px;}
        .dashboard-bottom-left, .dashboard-bottom-right { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px #0001; padding: 17px 16px 15px 16px; min-height: 320px; }
        .dashboard-bottom-left { flex: 2 1 420px; }
        .dashboard-bottom-right { flex: 1 1 320px; }
        @media (max-width: 1000px) {
            .dashboard-section { flex-direction: column; }
            .dashboard-chart-card, .dashboard-pie-card { min-width: 98vw;}
        }
        .card-amount .fa-peso-sign { font-size: 1.1rem; margin-right: 3px;}
    </style>
</head>
<body>
<div class="container pt-4 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h3 class="mb-0 font-weight-bold">Dashboard</h3>
        <form method="post" class="form-inline" <?= ($default_book_id <> 0 ? '' : 'hidden') ?>>
            <label class="mr-2 font-weight-bold" for="default_book_id">Default Book:</label>
            <select name="default_book_id" id="default_book_id" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php foreach($books as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $default_book_id==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Dashboard Cards -->
    <div class="dashboard-cards mb-3">
        <div class="dashboard-card col-lg-6">
            <div class="card-title"><i class="fa fa-wallet icon"></i>Net Balance</div>
            <div class="card-amount net"><i class="fa fa-peso-sign"></i><?= number_format($net_balance ?? 0,2) ?></div>
        </div>
        <div class="dashboard-card col-lg-6">
            <div class="card-title"><i class="fa fa-arrow-down arrow-in icon"></i>Cash In (Month)</div>
            <div class="card-amount in"><i class="fa fa-peso-sign"></i><?= number_format($month_in ?? 0,2) ?></div>
        </div>
        <div class="dashboard-card col-lg-6">
            <div class="card-title"><i class="fa fa-arrow-up arrow-out icon"></i>Cash Out (Month)</div>
            <div class="card-amount out"><i class="fa fa-peso-sign"></i><?= number_format($month_out ?? 0,2) ?></div>
        </div>
        <div class="dashboard-card col-lg-6">
            <div class="card-title"><i class="fa fa-file-lines icon tx"></i>Transactions (Month)</div>
            <div class="card-amount count"><?= $month_trans ?? 0 ?></div>
        </div>
    </div>

    <!-- Actions -->
    <div class="dashboard-actions mb-4" <?= ($default_book_id <> 0 ? '' : 'hidden') ?> >
        <a href="transactions.php?book_id=<?= $default_book_id ?>" class="btn btn-success"><i class="fa fa-plus-circle mr-2"></i>Add Transaction</a>
        <a href="reports_dashboard.php?book_id=<?= $default_book_id ?>" class="btn btn-info"><i class="fa fa-chart-bar mr-2"></i>View Reports</a>
        <a href="transactions_export.php?book_id=<?= $default_book_id ?>" class="btn btn-secondary"><i class="fa fa-file-excel mr-2"></i>Export Data</a>
    </div>

    <!-- Main Charts Section -->
    <div class="dashboard-section">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <!-- Line Chart -->
                <div class="flex-fill mb-4">
                    <div class="card-title font-weight-bold mb-3"><i class="fa fa-chart-line mr-2"></i>Cash Flow (current month)</div>
                    <canvas id="cashFlowChart" height="140"></canvas>
                </div>
            </div>
            
            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <!-- Pie Chart -->
                <div class="flex-fill mb-4">
                    <div class="card-title font-weight-bold mb-3"><i class="fa fa-chart-pie mr-2"></i>Expense Breakdown</div>
                    <canvas id="expensePieChart" height="140"></canvas>
                </div>
            </div>
        </div>
        
        
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script>
const cashflowLabels = <?= $cashflow_labels ?>;
const cashinData = <?= $cashflow_in ?>;
const cashoutData = <?= $cashflow_out ?>;

const ctx = document.getElementById('cashFlowChart').getContext('2d');
const cashFlowChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: cashflowLabels,
        datasets: [
            {
                label: "Cash In",
                data: cashinData,
                borderColor: "#25ac36",
                backgroundColor: "rgba(37,172,54,0.06)",
                fill: true,
                pointRadius: 2,
                borderWidth: 3,
                lineTension: 0.13,
            },
            {
                label: "Cash Out",
                data: cashoutData,
                borderColor: "#df3434",
                backgroundColor: "rgba(223,52,52,0.04)",
                fill: true,
                pointRadius: 2,
                borderWidth: 3,
                lineTension: 0.13,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            yAxes: [{
                ticks: { beginAtZero: true, fontSize: 13 }
            }],
            xAxes: [{
                ticks: { fontSize: 13 }
            }]
        },
        legend: {
            display: true,
            labels: { fontSize: 14 }
        }
    }
});

// Expense Pie Chart
const pieLabels = <?= json_encode($cat_labels) ?>;
const pieData = <?= json_encode($cat_data) ?>;
const pieColors = [
    "#5487f3","#77df9f","#ffbd6b","#df3434","#b87ec7","#f5da42","#6bc2ff","#00b894","#fd79a8","#a29bfe","#fdcb6e"
];
const ctxPie = document.getElementById('expensePieChart').getContext('2d');
const expensePieChart = new Chart(ctxPie, {
    type: 'pie',
    data: {
        labels: pieLabels,
        datasets: [{
            data: pieData,
            backgroundColor: pieColors,
        }]
    },
    options: {
        responsive: true,
        legend: { display: true, position: 'right', labels: { fontSize: 14 } }
    }
});
</script>
</body>
</html>
