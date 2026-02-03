// Patient card consultation datatable - dedicated columns for patient card context
// This file ONLY sets table_url and table_columns
// All functions come from datatable.js which is loaded first

var table_url = route('admin.patients.consultationsDatatable', {id: patientCardID});

// Use the same table_columns as datatable.js - the actions function is already defined there
var table_columns = window.consultation_table_columns;
