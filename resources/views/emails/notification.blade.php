<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #084 0%, #66a459 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px 20px;
        }
        .message {
            font-size: 16px;
            margin-bottom: 20px;
            color: #555;
        }
        .request-details {
            background: #f9f9f9;
            border-left: 4px solid #22c55e;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .request-details h3 {
            margin: 0 0 10px 0;
            color: #16a34a;
            font-size: 18px;
        }
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
            width: 140px;
            flex-shrink: 0;
        }
        .detail-value {
            color: #333;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
        }
        .button:hover {
            background: linear-gradient(135deg, #16a34a, #22c55e);
        }
        .footer {
            background: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #eee;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $title }}</h1>
        </div>

        <div class="content">
            <p>Hello {{ $user->name }},</p>

            <div class="message">
                {!! nl2br(e($message)) !!}
            </div>

            @if($request)
            <div class="request-details">
                <h3>Request Details</h3>
                <div class="detail-row">
                    <div class="detail-label">Request ID:</div>
                    <div class="detail-value">#{{ $request->id }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Title:</div>
                    <div class="detail-value">{{ $request->title }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value">{{ ucfirst($request->status) }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Submitted By:</div>
                    <div class="detail-value">{{ $request->user->name ?? 'N/A' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Department:</div>
                    <div class="detail-value">{{ $request->department->name ?? 'N/A' }}</div>
                </div>
                @if(isset($data['additional_info']))
                <div class="detail-row">
                    <div class="detail-label">Additional Info:</div>
                    <div class="detail-value">{{ $data['additional_info'] }}</div>
                </div>
                @endif
            </div>

            <div style="text-align: center;">
                <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/requests/{{ $request->id }}" class="button">
                    View Request
                </a>
            </div>
            @endif

            <p style="margin-top: 20px; color: #666; font-size: 14px;">
                You received this email because you have email notifications enabled for this type of event.
                You can manage your notification preferences in your account settings.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Workflow Management System. All rights reserved.</p>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
