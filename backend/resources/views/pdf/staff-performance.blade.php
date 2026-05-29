<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Staff Performance Report - {{ $department_name }}</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background-color: #4A5568;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #E2E8F0;
            font-size: 11px;
        }
        tr:hover {
            background-color: #F7FAFC;
        }
        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .role-doctor {
            background-color: #BEE3F8;
            color: #2C5282;
        }
        .role-nurse {
            background-color: #C6F6D5;
            color: #22543D;
        }
        .role-lab_technician {
            background-color: #FED7D7;
            color: #742A2A;
        }
        .role-pharmacist {
            background-color: #FEEBC8;
            color: #7C2D12;
        }
        .role-front_desk_officer {
            background-color: #E9D8FD;
            color: #44337A;
        }
        .role-department_staff {
            background-color: #E2E8F0;
            color: #2D3748;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 9px;
            color: #718096;
        }
        .metric-highlight {
            font-weight: bold;
            color: #2D3748;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital_name }}</h1>
        <h2>Staff Performance Report</h2>
        <p>{{ $department_name }}</p>
        <p>{{ $start_date }} to {{ $end_date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Staff Name</th>
                <th>Role</th>
                <th>Patients Handled</th>
                <th>Avg Service Time (min)</th>
                <th>Notes Added</th>
            </tr>
        </thead>
        <tbody>
            @foreach($staff_metrics as $staff)
            <tr>
                <td>{{ $staff['staff_name'] }}</td>
                <td>
                    <span class="role-badge role-{{ $staff['role'] }}">
                        {{ str_replace('_', ' ', $staff['role']) }}
                    </span>
                </td>
                <td class="metric-highlight">{{ $staff['patients_handled'] }}</td>
                <td class="metric-highlight">{{ $staff['average_service_time'] }}</td>
                <td>{{ $staff['notes_added'] }}</td>
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
