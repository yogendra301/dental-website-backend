<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin_model extends CI_Model {

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    // ============================================================
    // CLINIC OPERATIONS
    // ============================================================

    private $_jsonFields = ['services', 'working_hours', 'config', 'custom_slots', 'visibility_settings', 'reviews'];

    private function _parseClinic($row) {
        if (!$row) return null;
        foreach ($this->_jsonFields as $f) {
            if (isset($row[$f]) && is_string($row[$f])) {
                $row[$f] = json_decode($row[$f], true) ?: [];
            }
        }
        return $row;
    }

    public function getClinicByUsername($username, $select = '*') {
        $this->db->select($select);
        $query = $this->db->get_where('clinics', ['username' => $username]);
        return $this->_parseClinic($query->row_array());
    }

    // Keep for backward compatibility
    public function getClinicBySlug($slug, $select = '*') {
        return $this->getClinicByUsername($slug, $select);
    }

    public function getClinicById($id, $select = '*') {
        $this->db->select($select);
        $query = $this->db->get_where('clinics', ['id' => $id]);
        return $this->_parseClinic($query->row_array());
    }

    public function getAllClinics() {
        return $this->db
            ->select('id, username, name')
            ->order_by('name', 'ASC')
            ->get('clinics')
            ->result_array();
    }

    public function getClinicByCustomDomain($domain) {
        $query = $this->db->get_where('clinics', ['custom_domain' => $domain]);
        return $this->_parseClinic($query->row_array());
    }

    public function updateClinic($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('clinics', $data);
    }

    public function createClinic($data) {
        $this->db->insert('clinics', $data);
        return $this->db->insert_id();
    }

    public function updateClinicConfig($id, $config) {
        $this->db->set('config', json_encode($config));
        $this->db->where('id', $id);
        return $this->db->update('clinics');
    }

    public function updateClinicOtp($id, $otp, $expires) {
        $this->db->set('reset_otp', $otp);
        $this->db->set('reset_otp_expires', $expires);
        $this->db->where('id', $id);
        return $this->db->update('clinics');
    }

    public function updateClinicPhoneNumberId($id, $phoneNumberId) {
        $this->db->set('whatsapp_phone_number_id', $phoneNumberId);
        $this->db->where('id', $id);
        return $this->db->update('clinics');
    }

    public function clearClinicPhoneNumberId($id) {
        $this->db->set('whatsapp_phone_number_id', null);
        $this->db->where('id', $id);
        return $this->db->update('clinics');
    }

    // ============================================================
    // APPOINTMENT OPERATIONS
    // ============================================================

    public function getAppointments($clinicId, $date = null, $phone = null) {
        $this->db->where('clinic_id', $clinicId);
        if ($date) {
            $this->db->where('date', $date);
        }
        if ($phone) {
            $phone = $this->_normalizePhone($phone);
            $this->db->where('patient_phone', $phone);
        }
        $this->db->order_by('date DESC, time_slot ASC');
        $query = $this->db->get('appointments');
        return $query->result_array();
    }

    public function getAppointmentById($id, $clinicId) {
        $query = $this->db->get_where('appointments', ['id' => $id, 'clinic_id' => $clinicId]);
        return $query->row_array();
    }

    public function getAppointmentHistory($clinicId, $start, $end) {
        $this->db->where('clinic_id', $clinicId);
        $this->db->where('date >=', $start);
        $this->db->where('date <=', $end);
        $this->db->order_by('date DESC, time_slot ASC');
        $query = $this->db->get('appointments');
        return $query->result_array();
    }

    public function getFollowups($clinicId) {
        $sql = "SELECT * FROM appointments 
                WHERE clinic_id = ? AND follow_up_date IS NOT NULL 
                AND follow_up_completed = 0 
                AND follow_up_date <= CURDATE() + INTERVAL 7 DAY 
                ORDER BY follow_up_date ASC";
        $query = $this->db->query($sql, [$clinicId]);
        return $query->result_array();
    }

    public function checkDuplicateBooking($clinicId, $phone, $date) {
        $phone = $this->_normalizePhone($phone);
        $sql = "SELECT id FROM appointments 
                WHERE clinic_id = ? AND patient_phone = ? AND date = ? AND status != 'cancelled'";
        $query = $this->db->query($sql, [$clinicId, $phone, $date]);
        return $query->row_array();
    }

    public function checkBlockedSlot($clinicId, $date, $timeSlot) {
        $query = $this->db->get_where('slots_blocked', [
            'clinic_id' => $clinicId,
            'date' => $date,
            'time_slot' => $timeSlot
        ]);
        return $query->row_array();
    }

    public function checkSlotTaken($clinicId, $date, $timeSlot, $excludeId = null) {
        $this->db->where('clinic_id', $clinicId);
        $this->db->where('date', $date);
        $this->db->where('time_slot', $timeSlot);
        $this->db->where('status !=', 'cancelled');
        if ($excludeId) {
            $this->db->where('id !=', $excludeId);
        }
        $query = $this->db->get('appointments');
        return $query->row_array();
    }

    public function createAppointment($data) {
        $this->db->insert('appointments', $data);
        return $this->db->insert_id();
    }

    public function updateAppointment($id, $clinicId, $data) {
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->update('appointments', $data);
    }

    public function cancelAppointment($id, $clinicId) {
        $this->db->set('status', 'cancelled');
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->update('appointments');
    }

    public function deleteAppointmentHard($id, $clinicId) {
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->delete('appointments');
    }

    public function update_appointment_status($id, $clinicId, $status) {
        $this->db->set('status', $status);
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->update('appointments');
    }

    public function completeAppointment($id, $clinicId, $data) {
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->update('appointments', $data);
    }

    public function markFollowupDone($id, $clinicId) {
        $this->db->set('follow_up_completed', 1);
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->update('appointments');
    }

    public function lookupAppointments($clinicId, $phone) {
        $phone = $this->_normalizePhone($phone);
        $sql = "SELECT id, patient_name, patient_phone, service, date, time_slot, status 
                FROM appointments 
                WHERE clinic_id = ? AND patient_phone = ? 
                AND status IN ('pending', 'confirmed') 
                AND date >= CURDATE() 
                ORDER BY date ASC, time_slot ASC";
        $query = $this->db->query($sql, [$clinicId, $phone]);
        return $query->result_array();
    }

    // ============================================================
    // SLOT OPERATIONS
    // ============================================================

    public function getBlockedSlots($clinicId, $date) {
        $query = $this->db->get_where('slots_blocked', [
            'clinic_id' => $clinicId,
            'date' => $date
        ]);
        return $query->result_array();
    }

    public function blockSlot($clinicId, $date, $timeSlot) {
        $data = [
            'clinic_id' => $clinicId,
            'date' => $date,
            'time_slot' => $timeSlot
        ];
        return $this->db->insert('slots_blocked', $data);
    }

    public function unblockSlot($clinicId, $date, $timeSlot) {
        $this->db->where('clinic_id', $clinicId);
        $this->db->where('date', $date);
        $this->db->where('time_slot', $timeSlot);
        return $this->db->delete('slots_blocked');
    }

    public function getAppointmentsForSlots($clinicId, $date) {
        $sql = "SELECT time_slot, source, patient_name, is_emergency, status 
                FROM appointments 
                WHERE clinic_id = ? AND date = ? AND status != 'cancelled'";
        $query = $this->db->query($sql, [$clinicId, $date]);
        return $query->result_array();
    }

    // ============================================================
    // PATIENT OPERATIONS
    // ============================================================

    public function getPatientStats($clinicId, $phone) {
        $phone = $this->_normalizePhone($phone);
        $sql = "SELECT COUNT(*) AS visits, MAX(date) AS lastVisitDate 
                FROM appointments 
                WHERE clinic_id = ? AND patient_phone = ? AND status = 'completed'";
        $query = $this->db->query($sql, [$clinicId, $phone]);
        return $query->row_array();
    }

    public function getPatientTreatments($clinicId, $phone) {
        $phone = $this->_normalizePhone($phone);
        $sql = "SELECT DISTINCT treatment_performed 
                FROM appointments 
                WHERE clinic_id = ? AND patient_phone = ? 
                AND status = 'completed' AND treatment_performed IS NOT NULL";
        $query = $this->db->query($sql, [$clinicId, $phone]);
        $results = $query->result_array();
        return array_column($results, 'treatment_performed');
    }

    public function searchPatients($clinicId, $query) {
        if (preg_match('/^\+?\d+$/', $query)) {
            $this->db->like('patient_phone', $query);
        } else {
            $this->db->like('patient_name', $query);
        }

        $this->db->select("
            patient_name,
            patient_phone,
            COUNT(CASE WHEN status='completed' THEN 1 END) AS visits,
            MAX(CASE WHEN status='completed' THEN date END) AS lastVisitDate,
            SUM(amount_paid) AS total_paid,
            SUM(CASE WHEN payment_status != 'paid' THEN (treatment_cost - discount - amount_paid) ELSE 0 END) AS outstanding_balance
        ");
        $this->db->where('clinic_id', $clinicId);
        $this->db->group_by('patient_phone, patient_name');
        $query = $this->db->get('appointments');
        return $query->result_array();
    }

    // ============================================================
    // LEAD OPERATIONS
    // ============================================================

    public function getLeads($clinicId, $status = null) {
        $this->db->where('clinic_id', $clinicId);
        if ($status) {
            $this->db->where('status', $status);
        }
        $this->db->order_by('created_at', 'DESC');
        $query = $this->db->get('leads');
        return $query->result_array();
    }

    public function createLead($data) {
        return $this->db->insert('leads', $data);
    }

    public function updateLead($id, $clinicId, $data) {
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->update('leads', $data);
    }

    // ============================================================
    // REPORT OPERATIONS
    // ============================================================

    public function getReportSummary($clinicId, $start, $end) {
        $totalAppts = $this->db->query(
            "SELECT COUNT(*) AS count FROM appointments WHERE clinic_id = ? AND date BETWEEN ? AND ?",
            [$clinicId, $start, $end]
        )->row_array();

        $cancellations = $this->db->query(
            "SELECT COUNT(*) AS count FROM appointments WHERE clinic_id = ? AND date BETWEEN ? AND ? AND status = 'cancelled'",
            [$clinicId, $start, $end]
        )->row_array();

        $newPatients = $this->db->query(
            "SELECT COUNT(DISTINCT patient_phone) AS count 
             FROM appointments a1
             WHERE clinic_id = ? AND date BETWEEN ? AND ?
               AND NOT EXISTS (
                 SELECT 1 FROM appointments a2 
                 WHERE a2.clinic_id = a1.clinic_id 
                   AND a2.patient_phone = a1.patient_phone 
                   AND a2.status = 'completed' 
                   AND a2.date < ?
               )",
            [$clinicId, $start, $end, $start]
        )->row_array();

        $returningPatients = $this->db->query(
            "SELECT COUNT(DISTINCT patient_phone) AS count 
             FROM appointments a1
             WHERE clinic_id = ? AND date BETWEEN ? AND ?
               AND EXISTS (
                 SELECT 1 FROM appointments a2 
                 WHERE a2.clinic_id = a1.clinic_id 
                   AND a2.patient_phone = a1.patient_phone 
                   AND a2.status = 'completed' 
                   AND a2.date < ?
               )",
            [$clinicId, $start, $end, $start]
        )->row_array();

        $rev = $this->db->query(
            "SELECT SUM(amount_paid) AS sum FROM appointments WHERE clinic_id = ? AND date BETWEEN ? AND ? AND status = 'completed'",
            [$clinicId, $start, $end]
        )->row_array();

        $pendingPay = $this->db->query(
            "SELECT SUM(treatment_cost - discount - amount_paid) AS sum 
             FROM appointments 
             WHERE clinic_id = ? AND date BETWEEN ? AND ? AND status = 'completed' AND payment_status != 'paid'",
            [$clinicId, $start, $end]
        )->row_array();

        $serviceRev = $this->db->query(
            "SELECT service, SUM(amount_paid) AS revenue 
             FROM appointments 
             WHERE clinic_id = ? AND date BETWEEN ? AND ? AND status = 'completed'
             GROUP BY service",
            [$clinicId, $start, $end]
        )->result_array();

        $peakHours = $this->db->query(
            "SELECT SUBSTRING_INDEX(time_slot, ':', 1) AS hour, COUNT(*) AS count 
             FROM appointments 
             WHERE clinic_id = ? AND date BETWEEN ? AND ?
             GROUP BY hour 
             ORDER BY hour ASC",
            [$clinicId, $start, $end]
        )->result_array();

        $apptsCount = (int)($totalAppts['count'] ?? 0);
        $cancelledCount = (int)($cancellations['count'] ?? 0);
        $cancellationRate = $apptsCount > 0 ? round(($cancelledCount / $apptsCount) * 100, 2) : 0;

        return [
            'appointments_count' => $apptsCount,
            'new_patients_count' => (int)($newPatients['count'] ?? 0),
            'returning_patients_count' => (int)($returningPatients['count'] ?? 0),
            'revenue' => (float)($rev['sum'] ?? 0),
            'pending_payments' => (float)($pendingPay['sum'] ?? 0),
            'cancellation_count' => $cancelledCount,
            'cancellation_rate' => $cancellationRate,
            'service_revenue' => $serviceRev,
            'peak_hours' => $peakHours
        ];
    }

    // ============================================================
    // GALLERY OPERATIONS
    // ============================================================

    public function getGallery($clinicId) {
        $sql = "SELECT * FROM gallery WHERE clinic_id = ? 
                ORDER BY (CASE WHEN type = 'before_after' THEN 0 ELSE 1 END) ASC, created_at DESC";
        $query = $this->db->query($sql, [$clinicId]);
        return $query->result_array();
    }

    public function getGalleryByClinicUsername($username) {
        $sql = "SELECT g.* FROM gallery g 
                JOIN clinics c ON c.id = g.clinic_id 
                WHERE c.username = ? 
                ORDER BY (CASE WHEN g.type = 'before_after' THEN 0 ELSE 1 END) ASC, g.created_at DESC";
        $query = $this->db->query($sql, [$username]);
        return $query->result_array();
    }

    // Keep for backward compatibility
    public function getGalleryByClinicSlug($slug) {
        return $this->getGalleryByClinicUsername($slug);
    }

    public function createGalleryItem($data) {
        $allowed = ['single', 'single_ba', 'before_after'];
        if (!in_array($data['type'] ?? '', $allowed)) {
            $data['type'] = 'single';
        }
        return $this->db->insert('gallery', $data);
    }

    public function getGalleryItem($id, $clinicId) {
        $query = $this->db->get_where('gallery', ['id' => $id, 'clinic_id' => $clinicId]);
        return $query->row_array();
    }

    public function deleteGalleryItem($id) {
        $this->db->where('id', $id);
        return $this->db->delete('gallery');
    }

    // ============================================================
    // DOCUMENT OPERATIONS
    // ============================================================

    public function getDocuments($clinicId, $phone) {
        $phone = $this->_normalizePhone($phone);
        $this->db->where('clinic_id', $clinicId);
        $this->db->where('patient_phone', $phone);
        $this->db->order_by('uploaded_at', 'DESC');
        $query = $this->db->get('patient_documents');
        return $query->result_array();
    }

    public function createDocument($data) {
        $this->db->insert('patient_documents', $data);
        return $this->db->insert_id();
    }

    public function getDocument($id, $clinicId) {
        $query = $this->db->get_where('patient_documents', ['id' => $id, 'clinic_id' => $clinicId]);
        return $query->row_array();
    }

    public function deleteDocument($id, $clinicId) {
        $this->db->where('id', $id);
        $this->db->where('clinic_id', $clinicId);
        return $this->db->delete('patient_documents');
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function _normalizePhone($phone) {
        if (strpos($phone, '+91') === 0) {
            return $phone;
        }
        if (strpos($phone, '91') === 0 && strlen($phone) === 12) {
            return '+' . $phone;
        }
        return '+91' . $phone;
    }

    public function getPatientDashboard($clinicId) {
        // Today's appointments count & status breakdown
        $todayStr = date('Y-m-d');
        $todayAppts = $this->db->query(
            "SELECT status, COUNT(*) AS count FROM appointments WHERE clinic_id = ? AND date = ? GROUP BY status",
            [$clinicId, $todayStr]
        )->result_array();

        // Month stats (Current calendar month)
        $startMonth = date('Y-m-01');
        $endMonth = date('Y-m-t');

        $totalVisitsMonth = $this->db->query(
            "SELECT COUNT(*) AS count FROM appointments WHERE clinic_id = ? AND date BETWEEN ? AND ? AND status = 'completed'",
            [$clinicId, $startMonth, $endMonth]
        )->row_array();

        $newPatientsMonth = $this->db->query(
            "SELECT COUNT(DISTINCT patient_phone) AS count 
             FROM appointments a1
             WHERE clinic_id = ? AND date BETWEEN ? AND ?
               AND NOT EXISTS (
                 SELECT 1 FROM appointments a2 
                 WHERE a2.clinic_id = a1.clinic_id 
                   AND a2.patient_phone = a1.patient_phone 
                   AND a2.status = 'completed' 
                   AND a2.date < ?
               )",
            [$clinicId, $startMonth, $endMonth, $startMonth]
        )->row_array();

        $revenueMonth = $this->db->query(
            "SELECT SUM(amount_paid) AS sum FROM appointments WHERE clinic_id = ? AND date BETWEEN ? AND ? AND status = 'completed'",
            [$clinicId, $startMonth, $endMonth]
        )->row_array();

        $pendingPayMonth = $this->db->query(
            "SELECT SUM(treatment_cost - discount - amount_paid) AS sum 
             FROM appointments 
             WHERE clinic_id = ? AND date BETWEEN ? AND ? AND status = 'completed' AND payment_status != 'paid'",
            [$clinicId, $startMonth, $endMonth]
        )->row_array();

        // Top 10 Patients by Visits
        $topPatients = $this->db->query("
            SELECT 
                patient_name,
                patient_phone,
                COUNT(CASE WHEN status='completed' THEN 1 END) AS visits,
                MAX(CASE WHEN status='completed' THEN date END) AS lastVisitDate,
                SUM(amount_paid) AS total_paid,
                SUM(CASE WHEN payment_status != 'paid' THEN (treatment_cost - discount - amount_paid) ELSE 0 END) AS outstanding_balance
            FROM appointments
            WHERE clinic_id = ?
            GROUP BY patient_phone, patient_name
            ORDER BY visits DESC
            LIMIT 10
        ", [$clinicId])->result_array();

        // Recent patient activity (Last 5 completed)
        $recentActivity = $this->db->query("
            SELECT patient_name, patient_phone, service, date, time_slot, amount_paid, treatment_performed
            FROM appointments
            WHERE clinic_id = ? AND status = 'completed'
            ORDER BY date DESC, time_slot DESC
            LIMIT 5
        ", [$clinicId])->result_array();

        return [
            'today_appointments' => $todayAppts,
            'month_visits' => (int)($totalVisitsMonth['count'] ?? 0),
            'month_new_patients' => (int)($newPatientsMonth['count'] ?? 0),
            'month_revenue' => (float)($revenueMonth['sum'] ?? 0),
            'month_pending' => (float)($pendingPayMonth['sum'] ?? 0),
            'top_patients' => $topPatients,
            'recent_activity' => $recentActivity
        ];
    }

    public function parseJsonField($field) {
        if (is_string($field)) {
            return json_decode($field, true);
        }
        return $field ?: [];
    }
}