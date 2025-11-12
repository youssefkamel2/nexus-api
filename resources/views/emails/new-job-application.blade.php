<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Job Application</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }
        
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.95;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .alert-box {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .alert-box p {
            color: #1e40af;
            font-weight: 500;
            margin: 0;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6b7280;
            width: 180px;
            flex-shrink: 0;
        }
        
        .detail-value {
            color: #1f2937;
            flex: 1;
        }
        
        .message-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-top: 10px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 16px 40px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 30px 0;
            transition: transform 0.2s;
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .button-container {
            text-align: center;
        }
        
        .footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .badge {
            display: inline-block;
            background-color: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 20px;
                border-radius: 0;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>🎯 New Job Application</h1>
            <p>A new candidate has applied for a position</p>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Alert Box -->
            <div class="alert-box">
                <p>📬 You have received a new job application that requires your attention.</p>
            </div>
            
            <!-- Job Details Section -->
            <div class="section">
                <div class="section-title">📋 Job Position</div>
                <div class="detail-row">
                    <div class="detail-label">Position Title:</div>
                    <div class="detail-value"><strong>{{ $application->job->title }}</strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Location:</div>
                    <div class="detail-value">{{ $application->job->location }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Job Type:</div>
                    <div class="detail-value">
                        <span class="badge">{{ strtoupper($application->job->type) }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Applicant Details Section -->
            <div class="section">
                <div class="section-title">👤 Applicant Information</div>
                <div class="detail-row">
                    <div class="detail-label">Full Name:</div>
                    <div class="detail-value"><strong>{{ $application->name }}</strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email Address:</div>
                    <div class="detail-value"><a href="mailto:{{ $application->email }}">{{ $application->email }}</a></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Phone Number:</div>
                    <div class="detail-value"><a href="tel:{{ $application->phone }}">{{ $application->phone }}</a></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Years of Experience:</div>
                    <div class="detail-value">{{ $application->years_of_experience }} year(s)</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Availability:</div>
                    <div class="detail-value">{{ $application->availability }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Applied On:</div>
                    <div class="detail-value">{{ $application->created_at->format('F j, Y \a\t g:i A') }}</div>
                </div>
            </div>
            
            <!-- Message Section -->
            @if($application->message)
            <div class="section">
                <div class="section-title">💬 Applicant's Message</div>
                <div class="message-box">{{ $application->message }}</div>
            </div>
            @endif
            
            <!-- CTA Button -->
            <div class="button-container">
                <a href="{{ $dashboardLink }}" class="cta-button">
                    View Application Details →
                </a>
            </div>
            
            <p style="text-align: center; color: #6b7280; font-size: 14px; margin-top: 20px;">
                Click the button above to review the full application and download the CV from your dashboard.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p><strong>Nexus Engineering Consultancy</strong></p>
            <p>Dashboard: <a href="{{ $dashboardLink }}">{{ $dashboardLink }}</a></p>
            <p style="margin-top: 15px; font-size: 12px;">
                This is an automated notification. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
