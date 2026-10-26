-- ============================================================
-- HelpKonnect Seed Data
-- ============================================================

USE helpkonnect;

-- ------------------------------------------------------------
-- Roles
-- ------------------------------------------------------------
INSERT INTO roles (id, name, display_name) VALUES
  (1, 'super_admin', 'Super Admin'),
  (2, 'admin', 'Admin'),
  (3, 'support_staff', 'Support Staff'),
  (4, 'drawing_staff', 'Drawing Staff'),
  (5, 'user', 'User')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- ------------------------------------------------------------
-- Permissions
-- ------------------------------------------------------------
INSERT INTO permissions (id, code, name) VALUES
  (1,  'manage_all',                 'Manage Everything'),
  (2,  'applications.view',          'View Applications'),
  (3,  'applications.manage',        'Manage Applications'),
  (4,  'drawings.view',              'View Drawing Requests'),
  (5,  'drawings.manage',            'Manage Drawing Requests'),
  (6,  'conversations.view',         'View Conversations'),
  (7,  'conversations.manage',       'Manage Conversations'),
  (8,  'content.manage',             'Manage Content (FAQ/Docs/Announcements)'),
  (9,  'users.manage',               'Manage Users'),
  (10, 'settings.manage',            'Manage Settings'),
  (11, 'activity_logs.view',         'View Activity Logs'),
  (12, 'roles.manage',               'Manage Roles & Permissions'),
  (13, 'application_types.manage',   'Manage Application Types')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- Role-Permissions mapping
-- ------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
  -- Super Admin: everything
  (1, 1),(1, 2),(1, 3),(1, 4),(1, 5),(1, 6),(1, 7),(1, 8),(1, 9),(1, 10),(1, 11),(1, 12),(1, 13),
  -- Admin
  (2, 2),(2, 3),(2, 4),(2, 5),(2, 6),(2, 7),(2, 8),(2, 9),(2, 10),(2, 13),
  -- Support Staff: conversations + applications
  (3, 2),(3, 3),(3, 6),(3, 7),
  -- Drawing Staff
  (4, 4),(4, 5);

-- ------------------------------------------------------------
-- Default admin user
-- Password for all seeded users: 'Admin@12345'
-- ------------------------------------------------------------
INSERT INTO users (id, role_id, full_name, phone, email, password_hash, is_active, is_staff)
VALUES (
   1,
   1,
   'System Administrator',
   '+255700000000',
   'admin@helpkonnect.com',
   '$2y$10$eo2D8KWl5sKqT8ZG0z8h3eFwW7tWn3uKq8cZ6m4x9yX2w0sG1Qa2u',  -- placeholder, updated below
   1,
   1
)
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id), full_name = VALUES(full_name), phone = VALUES(phone), email = VALUES(email), is_active = VALUES(is_active), is_staff = VALUES(is_staff);

-- Re-hash admin password with a deterministic bcrypt value for 'Admin@12345'
UPDATE users
SET password_hash = '$2y$10$1kw5SvHhUbjdztiU6kXcT.jlMVNY26QS44u1PLajhZVgmrX1tBEPm'
WHERE id = 1;

-- ------------------------------------------------------------
-- Application types
-- ------------------------------------------------------------
INSERT INTO application_types (id, name, description, is_active, sort_order) VALUES
  (1, 'New Electricity Application', 'Apply for a new electricity connection.', 1, 1),
  (2, 'Electrical Installation Application', 'Application for an electrical installation.', 1, 2),
  (3, 'Application Drawing', 'Request an electrical/application drawing.', 1, 3),
  (4, 'Electrical Inspection Assistance', 'Request assistance with an electrical inspection.', 1, 4),
  (5, 'Connection Assistance', 'Get help with an electricity connection.', 1, 5),
  (6, 'Technical Assistance', 'Request general technical support.', 1, 6),
  (7, 'Other Request', 'Any other kind of request.', 1, 7)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- Document categories
-- ------------------------------------------------------------
INSERT INTO document_categories (id, name, slug) VALUES
  (1, 'Application Forms', 'application-forms'),
  (2, 'Application Guides', 'application-guides'),
  (3, 'Regulations & Guidelines', 'regulations-guidelines'),
  (4, 'Required Documents', 'required-documents'),
  (5, 'Safety Information', 'safety-information'),
  (6, 'Technical Resources', 'technical-resources')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- FAQ categories
-- ------------------------------------------------------------
INSERT INTO faq_categories (id, name, slug) VALUES
  (1, 'Applications', 'applications'),
  (2, 'Drawings', 'drawings'),
  (3, 'Electrical Installation', 'electrical-installation'),
  (4, 'Documents', 'documents'),
  (5, 'Payments', 'payments'),
  (6, 'Support', 'support'),
  (7, 'General Questions', 'general-questions')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- Assistance categories
-- ------------------------------------------------------------
INSERT INTO assistance_categories (id, name, slug) VALUES
  (1, 'Application Guides', 'application-guides'),
  (2, 'Electrical Information', 'electrical-information'),
  (3, 'Installation Information', 'installation-information'),
  (4, 'Drawing Guidelines', 'drawing-guidelines'),
  (5, 'Required Documents', 'required-documents'),
  (6, 'Safety Information', 'safety-information'),
  (7, 'Frequently Asked Questions', 'frequently-asked-questions'),
  (8, 'Regulations & Guidelines', 'regulations-guidelines'),
  (9, 'Important Notices', 'important-notices'),
  (10, 'Technical Resources', 'technical-resources')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- Sample FAQs (published, real information for the platform)
-- ------------------------------------------------------------
INSERT INTO faqs (category_id, question, answer, is_published, sort_order) VALUES
  (1, 'How do I apply for a new electricity connection?', 'Submit a New Electricity Application through the "Apply Now" section. Fill in the multi-step form with your personal details, application information and any supporting documents, then submit. You will receive a reference number to track your request.', 1, 1),
  (1, 'What information do I need to provide in the application?', 'You will need your full name, phone number, email (if available), region, district, ward and street/village, plus the type of application and a description of the service required.', 1, 2),
  (2, 'How do I request an electrical drawing?', 'Use the "Request Drawing" section and complete the drawing request form. Our drawing team will review it and you will be able to download the completed drawing from your dashboard once ready.', 1, 1),
  (2, 'How long does a drawing request take?', 'The time depends on the complexity of the drawing. You can track the progress using the reference number you receive after submitting your request.', 1, 2),
  (3, 'What documents are required for an electrical installation?', 'This may include a national ID, site photos, existing electrical documents, property documents, sketches or any other supporting documents. The exact requirements depend on your application type.', 1, 1),
  (4, 'Where can I download application forms and guides?', 'Visit the "Documents" library on this website, search for the resource you need and download it as a PDF.', 1, 1),
  (6, 'How can I contact HelpKonnect admin for help?', 'Use the "Contact Admin" section, start a chat conversation, or use the official phone, WhatsApp and email contacts shown on the website.', 1, 1),
  (7, 'How do I track my application status?', 'Use your unique reference number in the "Track Request" section, or log in to your account dashboard to see all your applications and their statuses.', 1, 2)
ON DUPLICATE KEY UPDATE question = VALUES(question);

-- ------------------------------------------------------------
-- Sample Assistance articles
-- ------------------------------------------------------------
INSERT INTO assistance_articles (category_id, title, slug, content, is_published) VALUES
  (1, 'How to Apply for a New Electricity Connection', 'how-to-apply-new-connection',
   '<p>To apply for a new electricity connection on HelpKonnect:</p><ol><li>Click <strong>Apply Now</strong>.</li><li>Complete the applicant information in Step 1.</li><li>Provide the application details in Step 2.</li><li>Attach any supporting documents in Step 3.</li><li>Review your details in Step 4 and submit.</li></ol><p>You will receive a unique reference number (e.g. <code>NK-2026-000124</code>) that you can use to track your request.</p>', 1),
  (2, 'Understanding Your Electricity Application', 'understanding-electricity-application',
   '<p>Your application will move through several statuses: <strong>Submitted</strong>, <strong>Under Review</strong>, <strong>Processing</strong> and <strong>Completed</strong>. If we need more information, the status will change to <strong>Awaiting Information</strong>. You can check the latest status at any time using your reference number.</p>', 1)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- ------------------------------------------------------------
-- System settings
-- ------------------------------------------------------------
INSERT INTO system_settings (setting_key, setting_value) VALUES
  ('platform_name', 'HelpKonnect'),
  ('platform_tagline', 'Get electrical application support, drawings, information, and direct assistance.'),
  ('support_phone', '+255700000000'),
  ('support_email', 'support@helpkonnect.com'),
  ('application_email', 'applications@helpkonnect.com'),
  ('technical_support_email', 'technical@helpkonnect.com'),
  ('whatsapp_contact', '+255700000000'),
  ('address', 'Dar es Salaam, Tanzania'),
  ('working_hours', 'Monday - Friday: 8:00 AM - 5:00 PM (EAT)'),
  ('currency', 'TZS'),
  ('max_upload_size_mb', '10'),
  ('application_prefix', 'NK'),
  ('pagination_limit', '20'),
  ('poll_interval_seconds', '15'),
  ('primary_color', '#0b2a4a'),
  ('accent_color', '#0ea5e9'),
  ('notify_sms', '1'),
  ('notify_whatsapp', '1'),
  ('notify_call', '0'),
  ('notification_gateway', 'simulated')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
