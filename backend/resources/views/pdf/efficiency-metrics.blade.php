<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Efficiency Metrics Report - {{ $department_name }}</title>
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
        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        .metric-card {
            background-color: #F7FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            padding: 20px;
        }
        .metric-label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #2D3748;
            margin-bottom: 10px;
        }
        .trend-indicator {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .trend-improving {
            background-color: #C6F6D5;
            color: #22543D;
        }
        .trend-declining {
            background-color: #FED7D7;
            color: #742A2A;
        }
        .trend-stable {
            background-color: #E2E8F0;
            color: #4A5568;
        }
        .trend-details {
            font-size: 10px;
            color: #718096;
            margin-top: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 9px;
            color: #718096;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #2D3748;
            margin: 25px 0 15px 0;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital_name }}</h1>
        <h2>Department Efficiency Metrics</h2>
        <p>{{ $department_name }}</p>
        <p>{{ $start_date }} to {{ $end_date }}</p>
    </div>

    <div class="section-title">Key Performance Indicators</div>

    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">Average Wait Time</div>
            <div class="metric-value">{{ $metrics['average_wait_time'] }} min</div>
            @if(isset($trend_indicators['average_wait_time']))
            <span class="trend-indicator trend-{{ $trend_indicators['average_wait_time']['direction'] }}">
                {{ $trend_indicators['average_wait_time']['direction'] }}
            </span>
            <div class="trend-details">
                {{ $trend_indicators['average_wait_time']['percentage_change'] > 0 ? '+' : '' }}{{ $trend_indicators['average_wait_time']['percentage_change'] }}% vs previous period
            </div>
            @endif
        </div>

        <div class="metric-card">
            <div class="metric-label">Average Service Time</div>
            <div class="metric-value">{{ $metrics['average_service_time'] }} min</div>
            @if(isset($trend_indicators['average_service_time']))
            <span class="trend-indicator trend-{{ $trend_indicators['average_service_time']['direction'] }}">
                {{ $trend_indicators['average_service_time']['direction'] }}
            </span>
            <div class="trend-details">
                {{ $trend_indicators['average_service_time']['percentage_change'] > 0 ? '+' : '' }}{{ $trend_indicators['average_service_time']['percentage_change'] }}% vs previous period
            </div>
            @endif
        </div>

        <div class="metric-card">
            <div class="metric-label">Patient Throughput</div>
            <div class="metric-value">{{ $metrics['patient_throughput'] }} /day</div>
            @if(isset($trend_indicators['throughput']))
            <span class="trend-indicator trend-{{ $trend_indicators['throughput']['direction'] }}">
                {{ $trend_indicators['throughput']['direction'] }}
            </span>
            <div class="trend-details">
                {{ $trend_indicators['throughput']['percentage_change'] > 0 ? '+' : '' }}{{ $trend_indicators['throughput']['percentage_change'] }}% vs previous period
            </div>
            @endif
        </div>

        <div class="metric-card">
            <div class="metric-label">Staff Utilization</div>
            <div class="metric-value">{{ $metrics['staff_utilization'] }}%</div>
            @if(isset($trend_indicators['staff_utilization']))
            <span class="trend-indicator trend-{{ $trend_indicators['staff_utilization']['direction'] }}">
                {{ $trend_indicators['staff_utilization']['direction'] }}
            </span>
            <div class="trend-details">
                {{ $trend_indicators['staff_utilization']['percentage_change'] > 0 ? '+' : '' }}{{ $trend_indicators['staff_utilization']['percentage_change'] }}% vs previous period
            </div>
            @endif
        </div>
    </div>

    <div class="footer">
        <p>Generated on {{ $export_date }}</p>
        <p>{{ $hospital_name }} - {{ $department_name }}</p>
        <p><strong>Note:</strong> Trend indicators compare current period with previous period of equal length.</p>
    </div>
</body>
</html>
