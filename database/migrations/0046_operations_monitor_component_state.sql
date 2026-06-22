ALTER TABLE operations_monitor_state
    ADD COLUMN IF NOT EXISTS last_component_states_json LONGTEXT NULL AFTER last_boot_id;
