-- ============================================================
-- HelpKonnect Database Schema
-- MySQL 8+
-- ============================================================

CREATE DATABASE IF NOT EXISTS helpkonnect
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE helpkonnect;

-- ------------------------------------------------------------
-- Roles & Permissions (RBAC)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  display_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Users (client accounts + admin/staff accounts)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED DEFAULT NULL,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  applicant_type VARCHAR(60) DEFAULT NULL,
  region VARCHAR(100) DEFAULT NULL,
  district VARCHAR(100) DEFAULT NULL,
  ward VARCHAR(100) DEFAULT NULL,
  street VARCHAR(120) DEFAULT NULL,
  profile_pic VARCHAR(255) DEFAULT NULL,
  email_verified_at TIMESTAMP NULL DEFAULT NULL,
  phone_verified_at TIMESTAMP NULL DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_staff TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_phone (phone),
  KEY idx_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- API auth tokens (stateless bearer tokens)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auth_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(60) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_expires (expires_at),
  CONSTRAINT fk_at_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Password resets
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token (token),
  KEY idx_user (user_id),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Application types (admin-managed)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS application_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Applications (orders)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(40) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  application_type_id INT UNSIGNED DEFAULT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Submitted',
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(160) DEFAULT NULL,
  region VARCHAR(100) DEFAULT NULL,
  district VARCHAR(100) DEFAULT NULL,
  ward VARCHAR(100) DEFAULT NULL,
  street VARCHAR(120) DEFAULT NULL,
  applicant_type VARCHAR(60) DEFAULT NULL,
  service_required VARCHAR(200) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  location TEXT DEFAULT NULL,
  preferred_contact VARCHAR(30) DEFAULT NULL,
  additional_notes TEXT DEFAULT NULL,
  assigned_to BIGINT UNSIGNED DEFAULT NULL,
  timezone VARCHAR(50) DEFAULT 'Africa/Dar_es_Salaam',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_type (application_type_id),
  KEY idx_status (status),
  KEY idx_phone (phone),
  KEY idx_assigned (assigned_to),
  KEY idx_submitted (submitted_at),
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_app_type FOREIGN KEY (application_type_id) REFERENCES application_types(id) ON DELETE SET NULL,
  CONSTRAINT fk_app_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Application status history
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS application_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(40) DEFAULT NULL,
  new_status VARCHAR(40) NOT NULL,
  note TEXT DEFAULT NULL,
  changed_by BIGINT UNSIGNED DEFAULT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_app (application_id),
  CONSTRAINT fk_ash_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_ash_by FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Application attachments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS application_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  file_size BIGINT UNSIGNED DEFAULT 0,
  category VARCHAR(80) DEFAULT NULL,
  uploaded_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_app (application_id),
  CONSTRAINT fk_att_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Application internal notes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS application_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  note TEXT NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_app (application_id),
  CONSTRAINT fk_an_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_an_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Drawing requests
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS drawing_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(40) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Submitted',
  applicant_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(160) DEFAULT NULL,
  location TEXT DEFAULT NULL,
  property_type VARCHAR(80) DEFAULT NULL,
  electrical_requirement VARCHAR(200) DEFAULT NULL,
  number_of_rooms INT DEFAULT NULL,
  estimated_load VARCHAR(60) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  additional_instructions TEXT DEFAULT NULL,
  assigned_to BIGINT UNSIGNED DEFAULT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_status (status),
  KEY idx_assigned (assigned_to),
  CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_dr_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Drawing files (submitted + completed drawings)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS drawing_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  drawing_request_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  file_size BIGINT UNSIGNED DEFAULT 0,
  file_type VARCHAR(30) NOT NULL DEFAULT 'submitted',  -- submitted | completed
  uploaded_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_drawing (drawing_request_id),
  CONSTRAINT fk_df_dr FOREIGN KEY (drawing_request_id) REFERENCES drawing_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_df_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Drawing status history
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS drawing_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  drawing_request_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(40) DEFAULT NULL,
  new_status VARCHAR(40) NOT NULL,
  note TEXT DEFAULT NULL,
  changed_by BIGINT UNSIGNED DEFAULT NULL,
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dr (drawing_request_id),
  CONSTRAINT fk_dsh_dr FOREIGN KEY (drawing_request_id) REFERENCES drawing_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_dsh_by FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Conversations & Chat
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(40) DEFAULT NULL,
  subject VARCHAR(200) DEFAULT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'Open',  -- Open | Closed | Reopened
  user_id BIGINT UNSIGNED DEFAULT NULL,
  assigned_to BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_status (status),
  KEY idx_assigned (assigned_to),
  CONSTRAINT fk_conv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_conv_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conversation_participants (
  conversation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id),
  CONSTRAINT fk_cp_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED DEFAULT NULL,
  sender_type VARCHAR(20) NOT NULL DEFAULT 'user',  -- user | admin
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_conv (conversation_id),
  KEY idx_sender (sender_id),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Document library (PDFs)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS document_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) DEFAULT NULL,
  file_size BIGINT UNSIGNED DEFAULT 0,
  version VARCHAR(30) DEFAULT '1.0',
  download_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  uploaded_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_category (category_id),
  KEY idx_published (is_published),
  CONSTRAINT fk_doc_cat FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_doc_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- FAQs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faq_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS faqs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED DEFAULT NULL,
  question TEXT NOT NULL,
  answer TEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_category (category_id),
  KEY idx_published (is_published),
  CONSTRAINT fk_faq_cat FOREIGN KEY (category_id) REFERENCES faq_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Assistance / Knowledge articles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS assistance_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assistance_articles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) DEFAULT NULL,
  content LONGTEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_category (category_id),
  KEY idx_published (is_published),
  CONSTRAINT fk_art_cat FOREIGN KEY (category_id) REFERENCES assistance_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Announcements
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  content TEXT NOT NULL,
  category VARCHAR(80) DEFAULT 'Notice',
  priority VARCHAR(30) NOT NULL DEFAULT 'Normal',  -- Normal | Important | Urgent
  image_path VARCHAR(255) DEFAULT NULL,
  pdf_path VARCHAR(255) DEFAULT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  published_at TIMESTAMP NULL DEFAULT NULL,
  created_by BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_published (is_published),
  KEY idx_pubdate (published_at),
  CONSTRAINT fk_ann_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'info',
  reference_no VARCHAR(40) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id),
  KEY idx_read (is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Outbound customer notifications (SMS / WhatsApp / call)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel VARCHAR(20) NOT NULL,               -- sms | whatsapp | call | email
  recipient VARCHAR(190) NOT NULL,            -- phone number or email
  subject VARCHAR(200) DEFAULT NULL,          -- e.g. Application Status Updated
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'sent', -- sent | failed | delivered
  provider_ref VARCHAR(120) DEFAULT NULL,
  simulated TINYINT(1) NOT NULL DEFAULT 1,    -- 1 when logged via simulated gateway
  resource_type VARCHAR(40) DEFAULT NULL,     -- application | drawing | conversation
  resource_id BIGINT UNSIGNED DEFAULT NULL,
  reference_no VARCHAR(40) DEFAULT NULL,
  error TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_recipient (recipient),
  KEY idx_resource (resource_type, resource_id),
  KEY idx_channel (channel)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Admin activity logs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT UNSIGNED DEFAULT NULL,
  action VARCHAR(120) NOT NULL,
  resource VARCHAR(80) DEFAULT NULL,
  resource_id BIGINT UNSIGNED DEFAULT NULL,
  ip_address VARCHAR(60) DEFAULT NULL,
  details TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin (admin_id),
  KEY idx_action (action),
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- System settings (key/value)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Rate limiting (simple in-memory-ish persisted counter)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(190) NOT NULL,
  route VARCHAR(120) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 1,
  window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rate (identifier, route)
) ENGINE=InnoDB;
