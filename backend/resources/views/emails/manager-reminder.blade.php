<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Reminder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 30px 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
        .content {
            background: #f8f9fa;
            padding: 30px 20px;
            border-left: 1px solid #e0e0e0;
            border-right: 1px solid #e0e0e0;
        }
        .idea-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ff6b6b;
        }
        .urgency-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            text-align: center;
        }
        .footer {
            background: #343a40;
            color: #fff;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #ff6b6b;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
            font-weight: bold;
        }
        .time-badge {
            background: #ffc107;
            color: #333;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⏰ Approval Reminder</h1>
    </div>

    <div class="content">
        <p>Hello {{ $manager->name }},</p>

        <p>This is a friendly reminder that you have an idea awaiting your review.</p>

        <div class="urgency-box">
            <div class="time-badge">
                ⏱️ Waiting for {{ $hoursWaiting }} hours
            </div>
            <p style="margin: 10px 0 0 0;"><strong>Action Required</strong></p>
        </div>

        <div class="idea-details">
            <h3>{{ $idea->name }}</h3>
            <p><strong>Submitted by:</strong> {{ $idea->user->name }}</p>
            <p><strong>Department:</strong> {{ $departmentName }}</p>
            <p><strong>Description:</strong></p>
            <p>{{ \Illuminate\Support\Str::limit($idea->description, 200) }}</p>

            @if($idea->pdf_path)
                <p>📄 <strong>Attachment:</strong> PDF document included</p>
            @endif

            <p style="margin-top: 15px;">
                <strong>Arrived at your department:</strong>
                {{ \Carbon\Carbon::parse($idea->approvals->where('department.name', $departmentName)->first()->arrived_at)->format('M d, Y h:i A') }}
            </p>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="{{ config('app.frontend_url') }}/manager" class="button">
                Review Now
            </a>
        </div>

        <p style="color: #666; font-size: 14px; margin-top: 20px;">
            <strong>Note:</strong> Please review this idea at your earliest convenience to keep the approval process moving smoothly.
        </p>
    </div>

    <div class="footer">
        <p>Laravel Vue Workflow System</p>
        <p>This is an automated notification. Please do not reply to this email.</p>
    </div>
</body>
</html>
