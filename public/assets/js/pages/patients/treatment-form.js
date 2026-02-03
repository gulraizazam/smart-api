// Patient card treatment datatable - dedicated columns for patient card context
// This file ONLY sets table_url and table_columns
// All functions come from treatmentDatatable.js which is loaded first

var table_url = route('admin.patients.treatmentsDatatable', {id: patientCardID});

// Use the same table_columns as treatmentDatatable.js - the actions function is already defined there
var table_columns = window.treatment_table_columns;
