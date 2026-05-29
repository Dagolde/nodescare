<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Summary Report - {{ $department_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #4A5568;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 8px 0;
            color: #2D3748;
            font-size: 22px;
        }
        .header h2 {
            margin: 5px 0;
            color: #4A5568;
            font-size: 16px;
            font-weight: normal;
        }
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            background-color: #F7FAFC;
            padding: 15px;
            border-radius: 4px;
        }
        .stat-box {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2D3748;
        }
        .stat-label {
            font-size: 11px;
            color: #718096;
            margin-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background-color: #4A5568;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 10px;
        }
        tr:hover {
            background-color: #F7FAFC;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 9px;
            color: #718096;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital_name }}</h1>
        <h2>Daily Summary Report</h2>
        <p>{{ $department_name }} - {{ $date }}</p>
    </div>

    <div class="summary-stats">
        <div class="stat-box">
            <div class="stat-value">{{ $total_patients }}</div>
            <div class="stat-label">Total Patients</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $average_wait_time }} min</div>
            <div class="stat-label">Avg Wait Time</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $average_service_time }} min</div>
            <div class="stat-label">Avg Service Time</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>Patient #</th>
                <th>Arrival</th>
                <th>Departure</th>
                <th>Wait (min)</th>
                <th>Service (min)</th>
                <th>Chief Complaint</th>
                <th>Outcome</th>
            </tr>
        </thead>
        <tbody>
            @foreach($patient_list as $patient)
            <tr>
                <td>{{ $patient['patient_name'] }}</td>
                <td>{{ $patient['patient_number'] }}</td>
                <td>{{ $patient['time_of_arrival'] }}</td>
                <td>{{ $patient['time_of_departure'] ?? '-' }}</td>
                <td>{{ $patient['wait_time'] ?? '-' }}</td>
                <td>{{ $patient['service_time'] ?? '-' }}</td>
                <td>{{ $patient['chief_complaint'] }}</td>
                <td>{{ $patient['outcome'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on {{ $export_date }}</p>
        <p>{{ $hospital_name }} - {{ $department_name }}</p>
    </div>
</body>
</html>
