<?php
require_once 'auth_check.php';
require_once 'db_connect.php';

include 'navbar.php';

$user_id = $_SESSION['user_id'];

// Get books owned by user
$books = [];
$stmt = $conn->prepare("SELECT b.id, b.name FROM books b
                        JOIN book_users bu ON b.id = bu.book_id
                        WHERE bu.user_id = ? AND bu.role_level = 'owner'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $books[] = $row;
}
$currentMonth = date('m');
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Reports Dashboard</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
  <style>
    canvas { background: #fff; }
    select[multiple] { height: auto; min-height: 100px; }
  </style>
</head>
<body>
<div class="container mt-4">
  <h4>Reports Dashboard</h4>

  <form id="filter-form" class="form-row mb-4">
    <div class="col-md-4">
      <label>Books (You Own)</label>
      <select name="book_ids[]" class="form-control" multiple required>
        <?php foreach ($books as $b): ?>
          <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label>Month</label>
      <select name="month" class="form-control">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= ($m == $currentMonth ? 'selected' : '') ?>>
            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label>Year</label>
      <select name="year" class="form-control">
        <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
          <option value="<?= $y ?>"><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="col-md-4 align-self-end">
      <button type="submit" class="btn btn-primary">Update</button>
      <button type="button" class="btn btn-danger ml-2" id="btn-pdf">Download PDF</button>
    </div>
  </form>

  <div class="row text-center mb-4" id="summary-cards"></div>

  <div class="row">
    <div class="col-md-8">
      <h6>Cash In vs Cash Out vs Net Balance</h6>
      <canvas id="lineChart"></canvas>
    </div>
    <div class="col-md-4">
      <h6>Category Breakdown</h6>
      <canvas id="pieChart"></canvas>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
function loadReports() {
  const data = $('#filter-form').serialize();
  $.get('reports_data.php', data, function(response) {
    $('#summary-cards').html(`
      <div class="col-md-4"><div class="alert alert-success">Total Cash In<br><strong>₱${response.total_cashin}</strong></div></div>
      <div class="col-md-4"><div class="alert alert-danger">Total Cash Out<br><strong>₱${response.total_cashout}</strong></div></div>
      <div class="col-md-4"><div class="alert alert-info">Net Balance<br><strong>₱${response.net_balance}</strong></div></div>
    `);

    lineChart.data.labels = response.chart.labels;
    lineChart.data.datasets[0].data = response.chart.cashin;
    lineChart.data.datasets[1].data = response.chart.cashout;
    lineChart.data.datasets[2].data = response.chart.net;
    lineChart.update();

    pieChart.data.labels = response.categories.labels;
    pieChart.data.datasets[0].data = response.categories.values;
    pieChart.update();
  }, 'json');
}

let lineChart = new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: [],
    datasets: [
      { label: 'Cash In', borderColor: 'green', fill: false, data: [] },
      { label: 'Cash Out', borderColor: 'red', fill: false, data: [] },
      { label: 'Net', borderColor: 'blue', fill: false, data: [] }
    ]
  }
});

let pieChart = new Chart(document.getElementById('pieChart'), {
  type: 'pie',
  data: {
    labels: [],
    datasets: [{
      data: [],
      backgroundColor: ['#ff6384', '#36a2eb', '#ffce56', '#8bc34a', '#e91e63']
    }]
  }
});

$('#filter-form').submit(function(e) {
  e.preventDefault();
  loadReports();
});

$('#btn-pdf').click(function () {
  const params = $('#filter-form').serialize();
  window.open('reports_pdf.php?' + params, '_blank');
});

$(document).ready(function() {
  loadReports();
});
</script>
</body>
</html>
