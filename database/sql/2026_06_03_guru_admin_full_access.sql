-- Manual SQL handoff for guru full-admin access.
-- Safe to re-run. Use this when the server cannot run php artisan db:seed/migrate.
-- Set @guru_admin_username if you want this SQL to assign the role to one account.

SET @schema_name := DATABASE();
SET @guru_admin_username := NULL;
-- Example:
-- SET @guru_admin_username := 'username_guru';

SET @missing_required_tables := (
    SELECT GROUP_CONCAT(required_tables.table_name ORDER BY required_tables.table_name SEPARATOR ', ')
    FROM (
        SELECT 'roles' AS table_name
        UNION ALL SELECT 'permissions'
        UNION ALL SELECT 'role_has_permissions'
        UNION ALL SELECT 'model_has_roles'
        UNION ALL SELECT 'users'
    ) AS required_tables
    LEFT JOIN information_schema.tables AS existing_tables
      ON existing_tables.table_schema = @schema_name
     AND existing_tables.table_name = required_tables.table_name
    WHERE existing_tables.table_name IS NULL
);

SET @sql := IF(
    @missing_required_tables IS NULL,
    'SELECT 1',
    CONCAT('SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Missing required auth table(s): ', @missing_required_tables, '''')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO roles (name, guard_name, created_at, updated_at)
SELECT 'guru_admin', 'web', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE name = 'guru_admin' AND guard_name = 'web'
);

SET @guru_admin_role_id := (
    SELECT id FROM roles WHERE name = 'guru_admin' AND guard_name = 'web' LIMIT 1
);

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, @guru_admin_role_id
FROM permissions p
WHERE p.guard_name = 'web'
  AND @guru_admin_role_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM role_has_permissions rp
      WHERE rp.permission_id = p.id
        AND rp.role_id = @guru_admin_role_id
  );

INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT @guru_admin_role_id, 'App\\Models\\User', u.id
FROM users u
WHERE @guru_admin_username IS NOT NULL
  AND u.username = @guru_admin_username
  AND @guru_admin_role_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM model_has_roles mr
      WHERE mr.role_id = @guru_admin_role_id
        AND mr.model_type = 'App\\Models\\User'
        AND mr.model_id = u.id
  );

SELECT
    @guru_admin_role_id AS guru_admin_role_id,
    (SELECT COUNT(*) FROM role_has_permissions WHERE role_id = @guru_admin_role_id) AS guru_admin_permission_count,
    (SELECT COUNT(*)
     FROM model_has_roles mr
     JOIN users u ON u.id = mr.model_id
     WHERE mr.role_id = @guru_admin_role_id
       AND mr.model_type = 'App\\Models\\User'
       AND (@guru_admin_username IS NULL OR u.username = @guru_admin_username)
    ) AS guru_admin_assigned_users;
