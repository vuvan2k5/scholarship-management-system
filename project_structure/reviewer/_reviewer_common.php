<?php
if (!function_exists('statusBadge')) {
    function statusBadge($status) {
        $map = [
            'draft' => 'secondary',
            'submitted' => 'primary',
            'reviewing' => 'warning',
            'eligible' => 'success',
            'ineligible' => 'danger',
            'approved' => 'success',
            'rejected' => 'danger',
            'disbursed' => 'info',
            'pending' => 'warning',
            'accepted' => 'success',
            'need_more_info' => 'warning',
            'sent' => 'primary',
            'reviewed' => 'info',
            'closed' => 'secondary'
        ];
        return $map[$status] ?? 'secondary';
    }
}

if (!function_exists('reviewerCss')) {
    function reviewerCss() {
        ?>
        <style>
            .review-hero {
                background: linear-gradient(135deg, #0f172a, #2563eb);
                color: white;
                border-radius: 28px;
                padding: 34px;
                box-shadow: 0 20px 45px rgba(37,99,235,.25);
                position: relative;
                overflow: hidden;
            }
            .review-hero:after {
                content: "";
                position: absolute;
                width: 260px;
                height: 260px;
                right: -80px;
                top: -90px;
                background: rgba(255,255,255,.14);
                border-radius: 50%;
            }
            .metric-card {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 22px;
                padding: 22px;
                box-shadow: 0 10px 28px rgba(15,23,42,.06);
                height: 100%;
            }
            .metric-icon {
                width: 46px;
                height: 46px;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #eff6ff;
                color: #2563eb;
                font-size: 22px;
            }
            .glass-card {
                background: rgba(255,255,255,.92);
                border: 1px solid #e5e7eb;
                border-radius: 24px;
                box-shadow: 0 16px 40px rgba(15,23,42,.08);
            }
            .applicant-avatar {
                width: 44px;
                height: 44px;
                border-radius: 14px;
                background: linear-gradient(135deg,#2563eb,#60a5fa);
                color: white;
                display:flex;
                align-items:center;
                justify-content:center;
                font-weight:700;
            }
            .score-box {
                border-radius: 18px;
                padding: 18px;
                background: #f8fafc;
                border: 1px solid #e5e7eb;
            }
            .progress {
                height: 10px;
                border-radius: 99px;
            }
            .action-btn {
                border-radius: 14px;
                padding: 10px 16px;
                font-weight: 600;
            }
            .soft-table th {
                color: #64748b;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: .04em;
            }
            .soft-table td, .soft-table th {
                vertical-align: middle;
                padding: 15px;
            }
            .mini-label {
                font-size: 12px;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: .05em;
                font-weight: 700;
            }
        </style>
        <?php
    }
}