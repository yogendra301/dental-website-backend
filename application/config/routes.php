<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'home';
$route['translate_uri_dashes'] = FALSE;

// ============================================================
// API Routes - All mapped to Admin controller
// ============================================================

// Auth routes
$route['api/auth/login']['POST'] = 'admin/login';
$route['api/auth/super-login']['POST'] = 'admin/super_login';
$route['api/auth/doctor-login']['POST'] = 'admin/doctor_login';
$route['api/auth/forgot-password']['POST'] = 'admin/forgot_password';
$route['api/auth/reset-password']['POST'] = 'admin/reset_password';
$route['api/auth/change-password']['PATCH'] = 'admin/change_password';

// Clinic routes
$route['api/clinics']['POST'] = 'admin/list_clinics';
$route['api/clinics/resolve']['GET'] = 'admin/resolve_clinic';
$route['api/clinics/settings']['PATCH'] = 'admin/update_settings';
$route['api/clinics/(:num)/whatsapp/connect']['POST'] = 'admin/whatsapp_connect/$1';
$route['api/clinics/(:num)/whatsapp/disconnect']['POST'] = 'admin/whatsapp_disconnect/$1';
$route['api/clinics/(:any)']['GET'] = 'admin/get_clinic/$1';

// Super admin routes
$route['api/super/clinics/create']['POST'] = 'admin/create_clinic';
$route['api/super/clinics/(:num)']['get'] = 'admin/get_clinic_full/$1';
$route['api/super/clinics/(:num)']['patch'] = 'admin/update_clinic_full/$1';

// Appointment routes — literals BEFORE wildcards
$route['api/appointments/lookup']['GET']                 = 'admin/lookup_appointments';
$route['api/appointments/history']['GET']                = 'admin/get_appointment_history';
$route['api/appointments/followups']['GET']              = 'admin/get_followups';
$route['api/appointments/admin']['POST']                 = 'admin/create_admin_appointment';
$route['api/appointments/(:num)/reschedule']['PATCH']    = 'admin/reschedule_appointment/$1';
$route['api/appointments/(:num)/cancel']['PATCH']        = 'admin/cancel_appointment/$1';
$route['api/appointments/(:num)/complete']['PATCH']      = 'admin/complete_appointment/$1';
$route['api/appointments/(:num)/followup-done']['PATCH'] = 'admin/followup_done/$1';
$route['api/appointments/(:num)/no-show']['PATCH']       = 'admin/no_show_appointment/$1';
$route['api/appointments/(:num)/request-review']['POST'] = 'admin/request_review/$1';
$route['api/appointments/(:num)']['PATCH']               = 'admin/update_appointment/$1';
$route['api/appointments/(:num)']['DELETE']              = 'admin/delete_appointment/$1';
$route['api/appointments']['GET']                        = 'admin/get_appointments';
$route['api/appointments']['POST']                       = 'admin/create_appointment';

// Slot routes
$route['api/slots/availability']['GET'] = 'admin/get_available_slots';
$route['api/slots/block']['POST'] = 'admin/block_slot';
$route['api/slots/block']['DELETE'] = 'admin/unblock_slot';

// Patient routes
$route['api/patients/lookup']['GET'] = 'admin/patient_lookup';
$route['api/patients/search']['GET'] = 'admin/patient_search';

// Lead routes
$route['api/leads']['GET'] = 'admin/get_leads';
$route['api/leads']['POST'] = 'admin/create_lead';
$route['api/leads/(:num)']['PATCH'] = 'admin/update_lead/$1';

// Report routes
$route['api/reports/summary']['GET'] = 'admin/report_summary';

// Homepage
$route['404_override'] = 'home';
$route['home'] = 'home';

// Gallery routes
$route['api/gallery']['GET'] = 'admin/get_gallery';
$route['api/gallery']['POST'] = 'admin/upload_gallery';
$route['api/gallery/before-after']['POST'] = 'admin/upload_before_after';
$route['api/gallery/(:num)/caption']['PATCH'] = 'admin/update_gallery_caption/$1';
$route['api/gallery/(:num)']['DELETE'] = 'admin/delete_gallery/$1';

// Asset upload (super admin config panel)
$route['api/upload/asset']['POST'] = 'admin/upload_asset';

// Document routes
$route['api/documents']['GET'] = 'admin/get_documents';
$route['api/documents']['POST'] = 'admin/upload_document';
$route['api/documents/(:num)']['DELETE'] = 'admin/delete_document/$1';