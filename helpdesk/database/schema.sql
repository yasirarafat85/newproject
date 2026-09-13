-- =====================================================================
--  HelpDesk — সম্পূর্ণ ডেটাবেস স্কিমা
--  MySQL 8.0+ / MariaDB 10.6+ · InnoDB · utf8mb4
--
--  কনভেনশন
--   · PK             : id BIGINT UNSIGNED AUTO_INCREMENT
--   · ইউনিক ইমেইল    : VARCHAR(190) — পুরনো MariaDB-র ইনডেক্স সীমার জন্য
--   · lookup FK      : ON DELETE SET NULL   (রেফারেন্স হারালেও রো টেকে)
--   · মালিকানা FK    : ON DELETE CASCADE    (প্যারেন্ট গেলে চাইল্ডও যায়)
--   · সুরক্ষিত FK     : ON DELETE RESTRICT   (ভুলে ডেটা হারানো ঠেকাতে)
--
--  চক্রাকার নির্ভরতা (departments ↔ agents ↔ email_accounts) ফাইলের
--  শেষে ALTER TABLE দিয়ে যোগ করা হয়েছে।
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- ১. রোল ও পারমিশন
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(80)  NOT NULL,
    description  VARCHAR(255) NULL,
    is_system    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL,
    updated_at   DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(64)  NOT NULL,
    group_name  VARCHAR(32)  NOT NULL,
    label       VARCHAR(160) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code),
    KEY ix_permissions_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY ix_rp_permission (permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ২. বিজনেস আওয়ার ও SLA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS business_hours (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80) NOT NULL,
    timezone   VARCHAR(64) NOT NULL DEFAULT 'Asia/Dhaka',
    is_default TINYINT(1)  NOT NULL DEFAULT 0,
    created_at DATETIME    NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_hours_slots (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_hours_id BIGINT UNSIGNED NOT NULL,
    day_of_week       TINYINT UNSIGNED NOT NULL COMMENT '0=রবিবার … 6=শনিবার',
    open_time         TIME NOT NULL,
    close_time        TIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_bhs_parent (business_hours_id, day_of_week),
    CONSTRAINT fk_bhs_parent FOREIGN KEY (business_hours_id) REFERENCES business_hours (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_hours_id BIGINT UNSIGNED NOT NULL,
    holiday_date      DATE NOT NULL,
    name              VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_holiday (business_hours_id, holiday_date),
    CONSTRAINT fk_holiday_parent FOREIGN KEY (business_hours_id) REFERENCES business_hours (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sla_plans (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                   VARCHAR(80) NOT NULL,
    first_response_minutes INT UNSIGNED NOT NULL DEFAULT 240,
    resolution_minutes     INT UNSIGNED NOT NULL DEFAULT 1440,
    business_hours_id      BIGINT UNSIGNED NULL,
    pause_on_pending       TINYINT(1) NOT NULL DEFAULT 1,
    warn_at_percent        TINYINT UNSIGNED NOT NULL DEFAULT 75,
    escalate_on_breach     TINYINT(1) NOT NULL DEFAULT 0,
    escalate_to_dept_id    BIGINT UNSIGNED NULL,
    escalate_to_team_id    BIGINT UNSIGNED NULL,
    is_default             TINYINT(1) NOT NULL DEFAULT 0,
    is_active              TINYINT(1) NOT NULL DEFAULT 1,
    created_at             DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_sla_bh (business_hours_id),
    CONSTRAINT fk_sla_bh FOREIGN KEY (business_hours_id) REFERENCES business_hours (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ৩. সাংগঠনিক কাঠামো
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_accounts (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(80)  NOT NULL,
    email             VARCHAR(190) NOT NULL,
    dept_id           BIGINT UNSIGNED NULL,
    imap_host         VARCHAR(160) NULL,
    imap_port         SMALLINT UNSIGNED NULL DEFAULT 993,
    imap_encryption   ENUM('none','ssl','tls') NOT NULL DEFAULT 'ssl',
    imap_username     VARCHAR(190) NULL,
    imap_password_enc TEXT NULL COMMENT 'AES-256-GCM এনক্রিপ্টেড',
    imap_folder       VARCHAR(80) NOT NULL DEFAULT 'INBOX',
    fetch_enabled     TINYINT(1) NOT NULL DEFAULT 0,
    fetch_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    delete_after_fetch TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host         VARCHAR(160) NULL,
    smtp_port         SMALLINT UNSIGNED NULL DEFAULT 587,
    smtp_encryption   ENUM('none','ssl','tls') NOT NULL DEFAULT 'tls',
    smtp_username     VARCHAR(190) NULL,
    smtp_password_enc TEXT NULL,
    from_name         VARCHAR(120) NULL,
    is_default        TINYINT(1) NOT NULL DEFAULT 0,
    last_fetch_at     DATETIME NULL,
    last_error        VARCHAR(500) NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_accounts_email (email),
    KEY ix_email_accounts_dept (dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id           BIGINT UNSIGNED NULL,
    name                VARCHAR(120) NOT NULL,
    signature           TEXT NULL,
    email_account_id    BIGINT UNSIGNED NULL,
    manager_id          BIGINT UNSIGNED NULL,
    sla_id              BIGINT UNSIGNED NULL,
    auto_response       TINYINT(1) NOT NULL DEFAULT 1,
    is_public           TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'গ্রাহকের ফর্মে দেখা যাবে কি না',
    assignment_strategy ENUM('manual','round_robin','least_load') NOT NULL DEFAULT 'manual',
    is_default          TINYINT(1) NOT NULL DEFAULT 0,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    sort_order          SMALLINT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_name (name),
    KEY ix_departments_parent (parent_id),
    KEY ix_departments_sla (sla_id),
    KEY ix_departments_manager (manager_id),
    KEY ix_departments_email (email_account_id),
    CONSTRAINT fk_dept_parent FOREIGN KEY (parent_id) REFERENCES departments (id) ON DELETE SET NULL,
    CONSTRAINT fk_dept_sla    FOREIGN KEY (sla_id)    REFERENCES sla_plans (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agents (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid                CHAR(36) NOT NULL,
    name                VARCHAR(120) NOT NULL,
    username            VARCHAR(60)  NOT NULL,
    email               VARCHAR(190) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    avatar_path         VARCHAR(255) NULL,
    signature           TEXT NULL,
    mobile              VARCHAR(30) NULL,
    role_id             BIGINT UNSIGNED NOT NULL,
    primary_dept_id     BIGINT UNSIGNED NULL,
    is_admin            TINYINT(1) NOT NULL DEFAULT 0,
    max_open_tickets    SMALLINT UNSIGNED NULL COMMENT 'NULL = সীমাহীন',
    is_available        TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'অটো-অ্যাসাইনে অন্তর্ভুক্ত',
    status              ENUM('active','locked','disabled') NOT NULL DEFAULT 'active',
    totp_secret         VARCHAR(64) NULL,
    locale              VARCHAR(5) NOT NULL DEFAULT 'bn',
    last_login_at       DATETIME NULL,
    last_assigned_at    DATETIME NULL COMMENT 'round-robin ক্রম নির্ধারণে',
    password_changed_at DATETIME NULL,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_agents_username (username),
    UNIQUE KEY uq_agents_email (email),
    UNIQUE KEY uq_agents_uuid (uuid),
    KEY ix_agents_role (role_id),
    KEY ix_agents_dept (primary_dept_id),
    KEY ix_agents_status (status, deleted_at),
    CONSTRAINT fk_agents_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT,
    CONSTRAINT fk_agents_dept FOREIGN KEY (primary_dept_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agent_departments (
    agent_id       BIGINT UNSIGNED NOT NULL,
    dept_id        BIGINT UNSIGNED NOT NULL,
    role_id        BIGINT UNSIGNED NULL COMMENT 'এই ডিপার্টমেন্টে ভিন্ন রোল',
    is_manager     TINYINT(1) NOT NULL DEFAULT 0,
    alerts_enabled TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (agent_id, dept_id),
    KEY ix_ad_dept (dept_id),
    KEY ix_ad_role (role_id),
    CONSTRAINT fk_ad_agent FOREIGN KEY (agent_id) REFERENCES agents (id)      ON DELETE CASCADE,
    CONSTRAINT fk_ad_dept  FOREIGN KEY (dept_id)  REFERENCES departments (id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_role  FOREIGN KEY (role_id)  REFERENCES roles (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120) NOT NULL,
    lead_agent_id BIGINT UNSIGNED NULL,
    notify_lead   TINYINT(1) NOT NULL DEFAULT 1,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_name (name),
    KEY ix_teams_lead (lead_agent_id),
    CONSTRAINT fk_teams_lead FOREIGN KEY (lead_agent_id) REFERENCES agents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
    team_id  BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (team_id, agent_id),
    KEY ix_tm_agent (agent_id),
    CONSTRAINT fk_tm_team  FOREIGN KEY (team_id)  REFERENCES teams (id)  ON DELETE CASCADE,
    CONSTRAINT fk_tm_agent FOREIGN KEY (agent_id) REFERENCES agents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ৪. গ্রাহক
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS organizations (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(160) NOT NULL,
    domain          VARCHAR(120) NULL COMMENT 'ইমেইল ডোমেইন দিয়ে অটো-লিংক',
    notes           TEXT NULL,
    default_dept_id BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_org_name (name),
    KEY ix_org_domain (domain),
    CONSTRAINT fk_org_dept FOREIGN KEY (default_dept_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid              CHAR(36) NOT NULL,
    org_id            BIGINT UNSIGNED NULL,
    name              VARCHAR(120) NOT NULL,
    email             VARCHAR(190) NOT NULL,
    phone             VARCHAR(30) NULL,
    password_hash     VARCHAR(255) NULL COMMENT 'NULL = গেস্ট, শুধু ইমেইল+নম্বরে দেখবে',
    locale            VARCHAR(5) NOT NULL DEFAULT 'bn',
    timezone          VARCHAR(64) NOT NULL DEFAULT 'Asia/Dhaka',
    status            ENUM('active','locked','banned') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at     DATETIME NULL,
    created_at        DATETIME NOT NULL,
    updated_at        DATETIME NULL,
    deleted_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_uuid (uuid),
    KEY ix_users_org (org_id),
    KEY ix_users_status (status, deleted_at),
    CONSTRAINT fk_users_org FOREIGN KEY (org_id) REFERENCES organizations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ৫. টিকেট লুকআপ ও কাস্টম ফর্ম
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS priorities (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(40) NOT NULL,
    color      VARCHAR(9)  NOT NULL DEFAULT '#6B7280',
    level      TINYINT UNSIGNED NOT NULL DEFAULT 2,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_priorities_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS statuses (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(40) NOT NULL,
    state         ENUM('open','paused','resolved','closed','archived') NOT NULL DEFAULT 'open',
    color         VARCHAR(9) NOT NULL DEFAULT '#2B62C8',
    icon          VARCHAR(40) NULL,
    is_default    TINYINT(1) NOT NULL DEFAULT 0,
    allows_reopen TINYINT(1) NOT NULL DEFAULT 1,
    sort_order    SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_statuses_name (name),
    KEY ix_statuses_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS forms (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120) NOT NULL,
    title        VARCHAR(160) NULL,
    instructions VARCHAR(500) NULL,
    type         ENUM('ticket','user','org') NOT NULL DEFAULT 'ticket',
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_fields (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id     BIGINT UNSIGNED NOT NULL,
    field_key   VARCHAR(60) NOT NULL,
    label       VARCHAR(160) NOT NULL,
    hint        VARCHAR(255) NULL,
    type        ENUM('text','textarea','select','multiselect','checkbox','radio','date',
                     'datetime','number','email','phone','file','section','divider') NOT NULL DEFAULT 'text',
    config      JSON NULL COMMENT '{options, min, max, rows, placeholder, regex}',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    visibility  ENUM('all','agent_only','internal') NOT NULL DEFAULT 'all',
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_form_field_key (form_id, field_key),
    CONSTRAINT fk_ff_form FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_topics (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id           BIGINT UNSIGNED NULL,
    name                VARCHAR(160) NOT NULL,
    dept_id             BIGINT UNSIGNED NULL,
    priority_id         BIGINT UNSIGNED NULL,
    sla_id              BIGINT UNSIGNED NULL,
    form_id             BIGINT UNSIGNED NULL,
    auto_assign_agent_id BIGINT UNSIGNED NULL,
    auto_assign_team_id  BIGINT UNSIGNED NULL,
    is_public           TINYINT(1) NOT NULL DEFAULT 1,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    sort_order          SMALLINT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_topics_dept (dept_id),
    KEY ix_topics_parent (parent_id),
    CONSTRAINT fk_topic_parent   FOREIGN KEY (parent_id)   REFERENCES help_topics (id) ON DELETE SET NULL,
    CONSTRAINT fk_topic_dept     FOREIGN KEY (dept_id)     REFERENCES departments (id) ON DELETE SET NULL,
    CONSTRAINT fk_topic_priority FOREIGN KEY (priority_id) REFERENCES priorities (id)  ON DELETE SET NULL,
    CONSTRAINT fk_topic_sla      FOREIGN KEY (sla_id)      REFERENCES sla_plans (id)   ON DELETE SET NULL,
    CONSTRAINT fk_topic_form     FOREIGN KEY (form_id)     REFERENCES forms (id)       ON DELETE SET NULL,
    CONSTRAINT fk_topic_agent    FOREIGN KEY (auto_assign_agent_id) REFERENCES agents (id) ON DELETE SET NULL,
    CONSTRAINT fk_topic_team     FOREIGN KEY (auto_assign_team_id)  REFERENCES teams (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ৬. টিকেট কোর
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid               CHAR(36) NOT NULL,
    number             VARCHAR(20) NOT NULL COMMENT 'পাবলিক রেফারেন্স, যেমন 250913-0042',
    user_id            BIGINT UNSIGNED NOT NULL,
    org_id             BIGINT UNSIGNED NULL,
    dept_id            BIGINT UNSIGNED NOT NULL,
    topic_id           BIGINT UNSIGNED NULL,
    status_id          BIGINT UNSIGNED NOT NULL,
    priority_id        BIGINT UNSIGNED NOT NULL,
    sla_id             BIGINT UNSIGNED NULL,
    assigned_agent_id  BIGINT UNSIGNED NULL,
    assigned_team_id   BIGINT UNSIGNED NULL,
    source             ENUM('web','email','phone','api','agent') NOT NULL DEFAULT 'web',
    subject            VARCHAR(255) NOT NULL,
    first_response_at  DATETIME NULL,
    last_message_at    DATETIME NULL COMMENT 'গ্রাহকের শেষ বার্তা',
    last_response_at   DATETIME NULL COMMENT 'এজেন্টের শেষ উত্তর',
    response_due_at    DATETIME NULL,
    due_at             DATETIME NULL,
    sla_paused_at      DATETIME NULL,
    sla_paused_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    is_overdue         TINYINT(1) NOT NULL DEFAULT 0,
    is_answered        TINYINT(1) NOT NULL DEFAULT 0,
    locked_by          BIGINT UNSIGNED NULL COMMENT 'একই টিকেটে দুজন এজেন্ট একসাথে কাজ ঠেকাতে',
    lock_expires_at    DATETIME NULL,
    reopen_count       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    merged_into_id     BIGINT UNSIGNED NULL,
    closed_at          DATETIME NULL,
    closed_by          BIGINT UNSIGNED NULL,
    created_by_agent_id BIGINT UNSIGNED NULL COMMENT 'এজেন্ট গ্রাহকের হয়ে খুললে',
    ip_address         VARCHAR(45) NULL,
    created_at         DATETIME NOT NULL,
    updated_at         DATETIME NULL,
    deleted_at         DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_number (number),
    UNIQUE KEY uq_tickets_uuid (uuid),
    KEY ix_tickets_queue (status_id, dept_id, assigned_agent_id),
    KEY ix_tickets_user (user_id, created_at),
    KEY ix_tickets_sla (due_at, is_overdue),
    KEY ix_tickets_assigned (assigned_agent_id, status_id),
    KEY ix_tickets_team (assigned_team_id, status_id),
    KEY ix_tickets_created (created_at),
    FULLTEXT KEY ft_tickets_subject (subject),
    CONSTRAINT fk_tickets_user     FOREIGN KEY (user_id)     REFERENCES users (id)         ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_org      FOREIGN KEY (org_id)      REFERENCES organizations (id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_dept     FOREIGN KEY (dept_id)     REFERENCES departments (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_topic    FOREIGN KEY (topic_id)    REFERENCES help_topics (id)   ON DELETE SET NULL,
    CONSTRAINT fk_tickets_status   FOREIGN KEY (status_id)   REFERENCES statuses (id)      ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_priority FOREIGN KEY (priority_id) REFERENCES priorities (id)    ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_sla      FOREIGN KEY (sla_id)      REFERENCES sla_plans (id)     ON DELETE SET NULL,
    CONSTRAINT fk_tickets_agent    FOREIGN KEY (assigned_agent_id) REFERENCES agents (id)  ON DELETE SET NULL,
    CONSTRAINT fk_tickets_team     FOREIGN KEY (assigned_team_id)  REFERENCES teams (id)   ON DELETE SET NULL,
    CONSTRAINT fk_tickets_merged   FOREIGN KEY (merged_into_id)    REFERENCES tickets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_threads (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id         BIGINT UNSIGNED NOT NULL,
    type              ENUM('message','response','note','system','forward') NOT NULL DEFAULT 'message',
    agent_id          BIGINT UNSIGNED NULL,
    user_id           BIGINT UNSIGNED NULL,
    body_html         MEDIUMTEXT NOT NULL,
    body_text         MEDIUMTEXT NULL,
    is_internal       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'গ্রাহক দেখবে না',
    source            ENUM('web','email','api','system') NOT NULL DEFAULT 'web',
    email_message_id  VARCHAR(255) NULL,
    email_in_reply_to VARCHAR(255) NULL,
    recipients        JSON NULL,
    ip_address        VARCHAR(45) NULL,
    edited_at         DATETIME NULL,
    edited_by         BIGINT UNSIGNED NULL,
    created_at        DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_threads_message_id (email_message_id),
    KEY ix_threads_ticket (ticket_id, created_at),
    KEY ix_threads_agent (agent_id),
    CONSTRAINT fk_threads_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_threads_agent  FOREIGN KEY (agent_id)  REFERENCES agents (id)  ON DELETE SET NULL,
    CONSTRAINT fk_threads_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid             CHAR(36) NOT NULL,
    ticket_id        BIGINT UNSIGNED NOT NULL,
    thread_id        BIGINT UNSIGNED NULL,
    original_name    VARCHAR(255) NOT NULL,
    stored_path      VARCHAR(255) NOT NULL COMMENT 'storage/attachments/YYYY/MM/<random>',
    mime_type        VARCHAR(120) NOT NULL,
    size_bytes       BIGINT UNSIGNED NOT NULL,
    checksum_sha256  CHAR(64) NULL,
    is_inline        TINYINT(1) NOT NULL DEFAULT 0,
    content_id       VARCHAR(255) NULL COMMENT 'ইমেইলের cid:',
    uploaded_by_type ENUM('agent','user','system') NOT NULL DEFAULT 'user',
    uploaded_by_id   BIGINT UNSIGNED NULL,
    created_at       DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attachments_uuid (uuid),
    KEY ix_attachments_ticket (ticket_id),
    KEY ix_attachments_thread (thread_id),
    CONSTRAINT fk_att_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id)        ON DELETE CASCADE,
    CONSTRAINT fk_att_thread FOREIGN KEY (thread_id) REFERENCES ticket_threads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_collaborators (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id          BIGINT UNSIGNED NOT NULL,
    user_id            BIGINT UNSIGNED NOT NULL,
    type               ENUM('cc','bcc') NOT NULL DEFAULT 'cc',
    added_by_agent_id  BIGINT UNSIGNED NULL,
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at         DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_collab (ticket_id, user_id),
    CONSTRAINT fk_collab_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_collab_user   FOREIGN KEY (user_id)   REFERENCES users (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_watchers (
    ticket_id  BIGINT UNSIGNED NOT NULL,
    agent_id   BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (ticket_id, agent_id),
    KEY ix_watchers_agent (agent_id),
    CONSTRAINT fk_watch_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_watch_agent  FOREIGN KEY (agent_id)  REFERENCES agents (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name  VARCHAR(60) NOT NULL,
    color VARCHAR(9) NOT NULL DEFAULT '#6B7280',
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_tags (
    ticket_id BIGINT UNSIGNED NOT NULL,
    tag_id    BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (ticket_id, tag_id),
    KEY ix_tt_tag (tag_id),
    CONSTRAINT fk_tt_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_tag    FOREIGN KEY (tag_id)    REFERENCES tags (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_links (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id        BIGINT UNSIGNED NOT NULL,
    linked_ticket_id BIGINT UNSIGNED NOT NULL,
    relation         ENUM('related','duplicate','blocks') NOT NULL DEFAULT 'related',
    created_at       DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_links (ticket_id, linked_ticket_id),
    KEY ix_links_linked (linked_ticket_id),
    CONSTRAINT fk_link_ticket FOREIGN KEY (ticket_id)        REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_link_other  FOREIGN KEY (linked_ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ৭. ফরওয়ার্ডিং ও এস্কেলেশন
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_transfers (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id     BIGINT UNSIGNED NOT NULL,
    transfer_type ENUM('department','agent','team','escalation','claim','release') NOT NULL,
    from_dept_id  BIGINT UNSIGNED NULL,
    to_dept_id    BIGINT UNSIGNED NULL,
    from_agent_id BIGINT UNSIGNED NULL,
    to_agent_id   BIGINT UNSIGNED NULL,
    from_team_id  BIGINT UNSIGNED NULL,
    to_team_id    BIGINT UNSIGNED NULL,
    reason        VARCHAR(255) NULL,
    note          TEXT NULL,
    by_agent_id   BIGINT UNSIGNED NULL COMMENT 'NULL = সিস্টেম/অটোমেশন',
    is_automatic  TINYINT(1) NOT NULL DEFAULT 0,
    sla_action    ENUM('keep','reset','extend') NOT NULL DEFAULT 'keep',
    old_due_at    DATETIME NULL,
    new_due_at    DATETIME NULL,
    created_at    DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_transfers_ticket (ticket_id, created_at),
    KEY ix_transfers_by (by_agent_id),
    CONSTRAINT fk_transfer_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_external_forwards (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id            BIGINT UNSIGNED NOT NULL,
    thread_id            BIGINT UNSIGNED NULL,
    to_email             VARCHAR(190) NOT NULL,
    cc_emails            VARCHAR(500) NULL,
    subject              VARCHAR(255) NOT NULL,
    body_html            MEDIUMTEXT NULL,
    include_attachments  TINYINT(1) NOT NULL DEFAULT 0,
    include_history      TINYINT(1) NOT NULL DEFAULT 0,
    reply_tracking_token VARCHAR(64) NOT NULL COMMENT 'উত্তর এলে এই টোকেনে টিকেট মেলানো হয়',
    by_agent_id          BIGINT UNSIGNED NULL,
    status               ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    error                VARCHAR(500) NULL,
    sent_at              DATETIME NULL,
    created_at           DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_forward_token (reply_tracking_token),
    KEY ix_forward_ticket (ticket_id),
    CONSTRAINT fk_fwd_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id)        ON DELETE CASCADE,
    CONSTRAINT fk_fwd_thread FOREIGN KEY (thread_id) REFERENCES ticket_threads (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS escalation_rules (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(120) NOT NULL,
    dept_id          BIGINT UNSIGNED NULL COMMENT 'NULL = সব ডিপার্টমেন্টে',
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    execution_order  SMALLINT NOT NULL DEFAULT 0,
    condition_type   ENUM('sla_breach','sla_warning','age','unassigned','no_response','priority') NOT NULL,
    condition_value  INT UNSIGNED NULL COMMENT 'age/no_response → মিনিট',
    match_priority_id BIGINT UNSIGNED NULL,
    match_status_id   BIGINT UNSIGNED NULL,
    action_type      ENUM('transfer_dept','assign_team','assign_agent','raise_priority','notify_manager','add_tag') NOT NULL,
    action_value     BIGINT UNSIGNED NULL,
    notify_emails    VARCHAR(500) NULL,
    created_at       DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_esc_dept (dept_id, is_active),
    CONSTRAINT fk_esc_dept FOREIGN KEY (dept_id) REFERENCES departments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ৮. অটোমেশন ও কনটেন্ট
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS filters (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(120) NOT NULL,
    execution_order SMALLINT NOT NULL DEFAULT 0,
    match_type      ENUM('all','any') NOT NULL DEFAULT 'all',
    target          ENUM('any','web','email','api') NOT NULL DEFAULT 'any',
    stop_on_match   TINYINT(1) NOT NULL DEFAULT 1,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_filters_order (is_active, execution_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS filter_rules (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filter_id BIGINT UNSIGNED NOT NULL,
    field     ENUM('from_email','from_name','subject','body','to_email','header','ip','org_domain') NOT NULL,
    operator  ENUM('equal','not_equal','contains','not_contains','starts_with','ends_with','regex') NOT NULL,
    value     VARCHAR(500) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_fr_filter (filter_id),
    CONSTRAINT fk_fr_filter FOREIGN KEY (filter_id) REFERENCES filters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS filter_actions (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    filter_id BIGINT UNSIGNED NOT NULL,
    action    ENUM('set_dept','set_topic','set_priority','set_sla','assign_agent','assign_team',
                   'set_status','add_tag','reject','disable_autoresponse','forward_email') NOT NULL,
    value     VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY ix_fa_filter (filter_id),
    CONSTRAINT fk_fa_filter FOREIGN KEY (filter_id) REFERENCES filters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canned_responses (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dept_id    BIGINT UNSIGNED NULL COMMENT 'NULL = সব ডিপার্টমেন্টে',
    title      VARCHAR(160) NOT NULL,
    body_html  MEDIUMTEXT NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_canned_dept (dept_id, is_active),
    CONSTRAINT fk_canned_dept  FOREIGN KEY (dept_id)    REFERENCES departments (id) ON DELETE CASCADE,
    CONSTRAINT fk_canned_agent FOREIGN KEY (created_by) REFERENCES agents (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_code VARCHAR(40) NOT NULL,
    code       VARCHAR(60) NOT NULL,
    locale     VARCHAR(5) NOT NULL DEFAULT 'bn',
    subject    VARCHAR(255) NOT NULL,
    body_html  MEDIUMTEXT NOT NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_template (code, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ৯. ইমেইল পাইপলাইন
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_queue (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    to_email         VARCHAR(190) NOT NULL,
    cc_emails        VARCHAR(500) NULL,
    bcc_emails       VARCHAR(500) NULL,
    from_account_id  BIGINT UNSIGNED NULL,
    subject          VARCHAR(255) NOT NULL,
    body_html        MEDIUMTEXT NOT NULL,
    body_text        MEDIUMTEXT NULL,
    attachments_json JSON NULL,
    ticket_id        BIGINT UNSIGNED NULL,
    thread_id        BIGINT UNSIGNED NULL,
    template_code    VARCHAR(60) NULL,
    priority         TINYINT UNSIGNED NOT NULL DEFAULT 5,
    attempts         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts     TINYINT UNSIGNED NOT NULL DEFAULT 3,
    status           ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    last_error       VARCHAR(500) NULL,
    scheduled_at     DATETIME NOT NULL,
    sent_at          DATETIME NULL,
    created_at       DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_queue_pending (status, scheduled_at, priority),
    KEY ix_queue_ticket (ticket_id),
    CONSTRAINT fk_queue_ticket  FOREIGN KEY (ticket_id)       REFERENCES tickets (id)        ON DELETE CASCADE,
    CONSTRAINT fk_queue_thread  FOREIGN KEY (thread_id)       REFERENCES ticket_threads (id) ON DELETE SET NULL,
    CONSTRAINT fk_queue_account FOREIGN KEY (from_account_id) REFERENCES email_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id  VARCHAR(255) NOT NULL,
    direction   ENUM('in','out') NOT NULL,
    ticket_id   BIGINT UNSIGNED NULL,
    from_email  VARCHAR(190) NULL,
    subject     VARCHAR(255) NULL,
    received_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_log_message (message_id),
    KEY ix_email_log_ticket (ticket_id),
    CONSTRAINT fk_elog_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ১০. কাস্টম ফর্মের মান
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS form_entries (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id     BIGINT UNSIGNED NOT NULL,
    object_type ENUM('ticket','user','org') NOT NULL,
    object_id   BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entry (form_id, object_type, object_id),
    KEY ix_entry_object (object_type, object_id),
    CONSTRAINT fk_entry_form FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_values (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id   BIGINT UNSIGNED NOT NULL,
    field_id   BIGINT UNSIGNED NOT NULL,
    value      TEXT NULL,
    value_json JSON NULL COMMENT 'multiselect/checkbox-এর একাধিক মান',
    PRIMARY KEY (id),
    UNIQUE KEY uq_value (entry_id, field_id),
    KEY ix_value_search (field_id, value(100)),
    CONSTRAINT fk_value_entry FOREIGN KEY (entry_id) REFERENCES form_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_value_field FOREIGN KEY (field_id) REFERENCES form_fields (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ১১. নলেজ বেস
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kb_categories (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id   BIGINT UNSIGNED NULL,
    name        VARCHAR(160) NOT NULL,
    slug        VARCHAR(190) NOT NULL,
    description VARCHAR(500) NULL,
    is_public   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kbcat_slug (slug),
    CONSTRAINT fk_kbcat_parent FOREIGN KEY (parent_id) REFERENCES kb_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kb_articles (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id  BIGINT UNSIGNED NULL,
    title        VARCHAR(255) NOT NULL,
    slug         VARCHAR(190) NOT NULL,
    body_html    MEDIUMTEXT NOT NULL,
    excerpt      VARCHAR(500) NULL,
    is_public    TINYINT(1) NOT NULL DEFAULT 0,
    is_featured  TINYINT(1) NOT NULL DEFAULT 0,
    views        INT UNSIGNED NOT NULL DEFAULT 0,
    helpful_yes  INT UNSIGNED NOT NULL DEFAULT 0,
    helpful_no   INT UNSIGNED NOT NULL DEFAULT 0,
    author_id    BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at   DATETIME NOT NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kb_slug (slug),
    KEY ix_kb_category (category_id, is_public),
    FULLTEXT KEY ft_kb_search (title, body_html),
    CONSTRAINT fk_kb_category FOREIGN KEY (category_id) REFERENCES kb_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_kb_author   FOREIGN KEY (author_id)   REFERENCES agents (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kb_article_topics (
    article_id BIGINT UNSIGNED NOT NULL,
    topic_id   BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, topic_id),
    KEY ix_kbat_topic (topic_id),
    CONSTRAINT fk_kbat_article FOREIGN KEY (article_id) REFERENCES kb_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_kbat_topic   FOREIGN KEY (topic_id)   REFERENCES help_topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ১২. সিস্টেম
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    group_name    VARCHAR(40) NOT NULL DEFAULT 'general',
    value_type    ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    updated_at    DATETIME NULL,
    PRIMARY KEY (setting_key),
    KEY ix_settings_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type  ENUM('agent','user','system') NOT NULL DEFAULT 'system',
    actor_id    BIGINT UNSIGNED NULL,
    action      VARCHAR(64) NOT NULL COMMENT 'ticket.transferred, agent.login …',
    object_type VARCHAR(40) NULL,
    object_id   BIGINT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    meta        JSON NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(500) NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_log_object (object_type, object_id, created_at),
    KEY ix_log_actor (actor_type, actor_id, created_at),
    KEY ix_log_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_id   BIGINT UNSIGNED NOT NULL,
    type       VARCHAR(40) NOT NULL,
    title      VARCHAR(190) NOT NULL,
    body       VARCHAR(500) NULL,
    url        VARCHAR(255) NULL,
    ticket_id  BIGINT UNSIGNED NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_notif_agent (agent_id, is_read, created_at),
    CONSTRAINT fk_notif_agent  FOREIGN KEY (agent_id)  REFERENCES agents (id)  ON DELETE CASCADE,
    CONSTRAINT fk_notif_ticket FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS saved_queues (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    agent_id   BIGINT UNSIGNED NULL COMMENT 'NULL = সবার জন্য শেয়ার্ড',
    name       VARCHAR(120) NOT NULL,
    criteria   JSON NOT NULL,
    columns    JSON NULL,
    sort_by    VARCHAR(40) NULL,
    sort_dir   ENUM('asc','desc') NOT NULL DEFAULT 'desc',
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_queues_agent (agent_id),
    CONSTRAINT fk_queues_agent FOREIGN KEY (agent_id) REFERENCES agents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_attempts_identifier (identifier, success, created_at),
    KEY ix_attempts_ip (ip_address, success, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type ENUM('agent','user') NOT NULL,
    email      VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_reset_token (token_hash),
    KEY ix_reset_email (actor_type, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_keys (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(120) NOT NULL,
    key_hash     CHAR(64) NOT NULL,
    ip_whitelist VARCHAR(500) NULL,
    scopes       JSON NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    last_used_at DATETIME NULL,
    created_at   DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_key (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cron_locks (
    job_name    VARCHAR(60) NOT NULL,
    locked_at   DATETIME NULL,
    locked_by   VARCHAR(64) NULL,
    last_run_at DATETIME NULL,
    last_status ENUM('ok','failed') NULL,
    last_output VARCHAR(1000) NULL,
    PRIMARY KEY (job_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ১৩. চক্রাকার নির্ভরতার FK (টেবিল তৈরি হওয়ার পর)
-- ---------------------------------------------------------------------
ALTER TABLE email_accounts
    ADD CONSTRAINT fk_email_accounts_dept FOREIGN KEY (dept_id) REFERENCES departments (id) ON DELETE SET NULL;

ALTER TABLE departments
    ADD CONSTRAINT fk_dept_manager FOREIGN KEY (manager_id)       REFERENCES agents (id)         ON DELETE SET NULL,
    ADD CONSTRAINT fk_dept_email   FOREIGN KEY (email_account_id) REFERENCES email_accounts (id) ON DELETE SET NULL;

ALTER TABLE sla_plans
    ADD CONSTRAINT fk_sla_esc_dept FOREIGN KEY (escalate_to_dept_id) REFERENCES departments (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_sla_esc_team FOREIGN KEY (escalate_to_team_id) REFERENCES teams (id)       ON DELETE SET NULL;

ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_closed_by FOREIGN KEY (closed_by)           REFERENCES agents (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tickets_creator   FOREIGN KEY (created_by_agent_id) REFERENCES agents (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tickets_locked_by FOREIGN KEY (locked_by)           REFERENCES agents (id) ON DELETE SET NULL;

ALTER TABLE ticket_transfers
    ADD CONSTRAINT fk_transfer_by_agent FOREIGN KEY (by_agent_id) REFERENCES agents (id) ON DELETE SET NULL;

ALTER TABLE ticket_external_forwards
    ADD CONSTRAINT fk_fwd_by_agent FOREIGN KEY (by_agent_id) REFERENCES agents (id) ON DELETE SET NULL;

ALTER TABLE ticket_collaborators
    ADD CONSTRAINT fk_collab_agent FOREIGN KEY (added_by_agent_id) REFERENCES agents (id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
