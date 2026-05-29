<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prescription - {{ $patient_number }}</title>
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
        .prescription-box {
            background-color: #F7FAFC;
            border: 2px solid #4299E1;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            min-height: 150px;
        }
        .prescription-content {
            font-size: 13px;
            line-height: 1.8;
            white-space: pre-wrap;
            color: #2D3748;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 10px;
            color: #718096;
        }
        .signature-section {
            margin-top: 60px;
            text-align: right;
        }
        .signature-line {
            border-top: 1px solid #2D3748;
            width: 200px;
            margin-left: auto;
            margin-top: 40px;
            padding-top: 5px;
            text-align: center;
            font-size: 11px;
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
        <h2>Medical Prescription</h2>
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
                <td class="info-label">Date:</td>
                <td class="info-value">{{ $prescription_date }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Prescribing Doctor</div>
        <table>
            <tr>
                <td class="info-label">Doctor:</td>
                <td class="info-value">{{ $doctor_name }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Prescription</div>
        <div class="prescription-box">
            <div class="prescription-content">{{ $prescription_notes }}</div>
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-line">
            Doctor's Signature
        </div>
    </div>

    <div class="footer">
        <p>This document was generated on {{ $export_date }}</p>
        <p>{{ $hospital_name }}</p>
        <p><strong>Note:</strong> This prescription is valid for 30 days from the date of issue.</p>
    </div>
</body>
</html>
