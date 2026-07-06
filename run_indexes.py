#!/usr/bin/env python3
import mysql.connector
from mysql.connector import errorcode

config = {
    'user': 'yogi',
    'password': 'Yogen@1234',
    'host': 'localhost',
    'database': 'dental_clinic',
    'raise_on_warnings': False
}

indexes = [
    ("clinics", "idx_username", "ALTER TABLE clinics ADD INDEX idx_username (username)"),
    ("clinics", "idx_custom_domain", "ALTER TABLE clinics ADD INDEX idx_custom_domain (custom_domain)"),
    ("appointments", "idx_clinic_date", "ALTER TABLE appointments ADD INDEX idx_clinic_date (clinic_id, date)"),
    ("appointments", "idx_clinic_phone_apt", "ALTER TABLE appointments ADD INDEX idx_clinic_phone_apt (clinic_id, patient_phone)"),
    ("appointments", "idx_clinic_date_status", "ALTER TABLE appointments ADD INDEX idx_clinic_date_status (clinic_id, date, status)"),
    ("appointments", "idx_clinic_slot", "ALTER TABLE appointments ADD INDEX idx_clinic_slot (clinic_id, date, time_slot)"),
    ("slots_blocked", "idx_clinic_date_slot", "ALTER TABLE slots_blocked ADD INDEX idx_clinic_date_slot (clinic_id, date, time_slot)"),
    ("gallery", "idx_clinic_id", "ALTER TABLE gallery ADD INDEX idx_clinic_id (clinic_id)"),
    # ("patient_documents", "idx_clinic_phone", "ALTER TABLE patient_documents ADD INDEX idx_clinic_phone (clinic_id, patient_phone)"),  # may exist
    ("leads", "idx_clinic_id_leads", "ALTER TABLE leads ADD INDEX idx_clinic_id_leads (clinic_id)"),
]

try:
    cnx = mysql.connector.connect(**config)
    cursor = cnx.cursor()
    for table, idx_name, sql in indexes:
        try:
            cursor.execute(sql)
            print(f"OK: {idx_name} on {table}")
        except mysql.connector.Error as e:
            if e.errno == 1061:  # Duplicate key name
                print(f"SKIP: {idx_name} already exists")
            else:
                print(f"ERR {idx_name}: {e}")
    cursor.close()
    cnx.close()
    print("Done.")
except mysql.connector.Error as e:
    print(f"Connection error: {e}")