<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lab Result - {{ $patient_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4A5568;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 10px 0;
            color: #2D3748;
            font-size: 24px;
        }
        .header h2 {
            margin: 5px 0;
            color: #4A5568;
            font-size: 18px;
            font-weight: normal;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2D3748;
            margin-bottom: 10px;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #4A5568;
        }
        .info-value {
            flex: 1;
            color: #2D3748;
        }
        .result-box {
            background-color: #F7FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .result-value {
            font-size: 18px;
            font-weight: bold;
            color: #2D3748;
            margin: 10px 0;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }
        .status-completed {
            background-color: #C6F6D5;
            color: #22543D;
        }
        .status-pending {
            background-color: #FED7D7;
            color: #742A2A;
        }
        .status-abnormal {
            background-color: #FED7D7;
            color: #742A2A;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 10px;
            color: #718096;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 8px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital_name }}</h1>
        <h2>Laboratory Test Result</h2>
    </div>

    <div class="section">
        <div class="section-title">Patient Information</div>
        <table>
            <tr>
                <td class="info-label">Patient Name:</td>
                <td class="info-value">{{ $patient_name }}</td>
            </tr>
            <tr>
                <td class="info-label">Patient Number:</td>
                <td class="info-value">{{ $patient_number }}</td>
            </tr>
            <tr>
                <td class="info-label">Test Date:</td>
                <td class="info-value">{{ $test_date }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Test Details</div>
        <table>
            <tr>
                <td class="info-label">Test Name:</td>
                <td class="info-value">{{ $test_name }}</td>
            </tr>
            <tr>
                <td class="info-label">Status:</td>
                <td class="info-value">
                    <span class="status status-{{ $status }}">{{ ucfirst($status) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="result-box">
        <div class="info-label">Result:</div>
        <div class="result-value">{{ $result }}</div>
        @if($unit)
        <div class="info-value">Unit: {{ $unit }}</div>
        @endif
        @if($reference_range)
        <div class="info-value">Reference Range: {{ $reference_range }}</div>
        @endif
    </div>

    @if($notes)
    <div class="section">
        <div class="section-title">Notes</div>
        <div class="info-value">{{ $notes }}</div>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Technician Information</div>
        <table>
            <tr>
                <td class="info-label">Technician:</td>
                <td class="info-value">{{ $technician_name }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>This document was generated on {{ $export_date }}</p>
        <p>{{ $hospital_name }} - Laboratory Department</p>
    </div>
</body>
</html>
