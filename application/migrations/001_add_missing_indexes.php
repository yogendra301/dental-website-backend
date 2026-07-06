<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_missing_indexes extends CI_Migration {

    public function up() {
        // Bug 9-13: Add missing indexes for query performance
        
        // Index on clinics.username for fast lookup
        $this->db->query("ALTER TABLE clinics ADD INDEX IF NOT EXISTS idx_username (username)");
        
        // Index on clinics.custom_domain for domain resolution
        $this->db->query("ALTER TABLE clinics ADD INDEX IF NOT EXISTS idx_custom_domain (custom_domain)");
        
        // Composite index for appointments queries (Bug 11, 12, 13)
        $this->db->query("ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_clinic_date (clinic_id, date)");
        $this->db->query("ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_clinic_phone (clinic_id, patient_phone)");
        $this->db->query("ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_clinic_date_status (clinic_id, date, status)");
        $this->db->query("ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_clinic_slot (clinic_id, date, time_slot)");
        
        // Index for slots_blocked queries (Bug 12)
        $this->db->query("ALTER TABLE slots_blocked ADD INDEX IF NOT EXISTS idx_clinic_date_slot (clinic_id, date, time_slot)");
        
        // Index for gallery queries
        $this->db->query("ALTER TABLE gallery ADD INDEX IF NOT EXISTS idx_clinic_id (clinic_id)");
        
        // Index for document queries
        $this->db->query("ALTER TABLE patient_documents ADD INDEX IF NOT EXISTS idx_clinic_phone (clinic_id, patient_phone)");
        
        // Index for leads
        $this->db->query("ALTER TABLE leads ADD INDEX IF NOT EXISTS idx_clinic_id (clinic_id)");
    }

    public function down() {
        // Remove indexes
        $this->db->query("ALTER TABLE clinics DROP INDEX IF EXISTS idx_username");
        $this->db->query("ALTER TABLE clinics DROP INDEX IF EXISTS idx_custom_domain");
        $this->db->query("ALTER TABLE appointments DROP INDEX IF EXISTS idx_clinic_date");
        $this->db->query("ALTER TABLE appointments DROP INDEX IF EXISTS idx_clinic_phone");
        $this->db->query("ALTER TABLE appointments DROP INDEX IF EXISTS idx_clinic_date_status");
        $this->db->query("ALTER TABLE appointments DROP INDEX IF EXISTS idx_clinic_slot");
        $this->db->query("ALTER TABLE slots_blocked DROP INDEX IF EXISTS idx_clinic_date_slot");
        $this->db->query("ALTER TABLE gallery DROP INDEX IF EXISTS idx_clinic_id");
        $this->db->query("ALTER TABLE patient_documents DROP INDEX IF EXISTS idx_clinic_phone");
        $this->db->query("ALTER TABLE leads DROP INDEX IF EXISTS idx_clinic_id");
    }
}