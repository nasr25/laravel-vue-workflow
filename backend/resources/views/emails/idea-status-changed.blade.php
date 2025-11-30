<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Idea Status Update</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 14px;
            margin: 10px 0;
        }
        .approved {
            background: #28a745;
            color: white;
        }
        .rejected {
            background: #dc3545;
            color: white;
        }
        .returned {
            background: #ffc107;
            color: #333;
        }
        .idea-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .comments-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
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
            background: #667eea;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔔 Idea Status Update</h1>
    </div>

    <div class="content">
        <p>Hello {{ $idea->user->name }},</p>

        <p>Your idea has been reviewed by <strong>{{ $departmentName }}</strong>.</p>

        <div class="status-badge {{ $action }}">
            @if($action === 'approved')
                ✓ Approved
            @elseif($action === 'rejected')
                ✗ Rejected
            @else
                ⟲ Returned for Revision
            @endif
        </div>

        <div class="idea-details">
            <h3>{{ $idea->name }}</h3>
            <p><strong>Current Status:</strong> {{ ucfirst($idea->status) }}</p>
            <p><strong>Progress:</strong> Step {{ $idea->current_approval_step }} of 4</p>

            @if($action === 'approved')
                <p style="color: #28a745;">
                    ✓ Your idea has been approved by {{ $departmentName }}!
                    @if($idea->status === 'approved')
                        <br><strong>Congratulations!</strong> Your idea has completed all approval steps.
                    @else
                        <br>It will now move to the next department for review.
                    @endif
                </p>
            @elseif($action === 'rejected')
                <p style="color: #dc3545;">
                    ✗ Unfortunately, your idea was not approved by {{ $departmentName }}.
                </p>
            @else
                <p style="color: #856404;">
                    ⟲ Your idea has been returned for revisions by {{ $departmentName }}.
                    <br>Please review the feedback and resubmit when ready.
                </p>
            @endif
        </div>

        @if($comments)
            <div class="comments-box">
                <strong>📝 Feedback from {{ $departmentName }}:</strong>
                <p>{{ $comments }}</p>
            </div>
        @endif

        @if($action === 'returned')
            <div style="text-align: center; margin-top: 20px;">
                <a href="{{ config('app.frontend_url') }}/user" class="button">
                    Edit Your Idea
                </a>
            </div>
        @endif
    </div>

    <div class="footer">
        <p>Laravel Vue Workflow System</p>
        <p>This is an automated notification. Please do not reply to this email.</p>
    </div>
</body>
</html>
