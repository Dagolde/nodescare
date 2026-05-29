/// Assembles a human-readable consultation note from structured clinical fields.
///
/// Each non-null, non-whitespace field is included with its label, separated
/// by double newlines. Fields that are null or contain only whitespace are omitted.
String assembleConsultationNote({
  String? complaintHistory,
  String? examinationFindings,
  String? diagnosis,
  String? prescriptionNotes,
  String? investigationNotes,
  String? additionalNotes,
}) {
  final parts = <String>[];
  if (complaintHistory != null && complaintHistory.trim().isNotEmpty)
    parts.add('Complaint & History: ${complaintHistory.trim()}');
  if (examinationFindings != null && examinationFindings.trim().isNotEmpty)
    parts.add('Examination Findings: ${examinationFindings.trim()}');
  if (diagnosis != null && diagnosis.trim().isNotEmpty)
    parts.add('Diagnosis: ${diagnosis.trim()}');
  if (prescriptionNotes != null && prescriptionNotes.trim().isNotEmpty)
    parts.add('Prescription: ${prescriptionNotes.trim()}');
  if (investigationNotes != null && investigationNotes.trim().isNotEmpty)
    parts.add('Investigations: ${investigationNotes.trim()}');
  if (additionalNotes != null && additionalNotes.trim().isNotEmpty)
    parts.add('Notes: ${additionalNotes.trim()}');
  return parts.join('\n\n');
}
