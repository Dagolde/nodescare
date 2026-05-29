import 'package:flutter/material.dart';
import '../../domain/entities/patient_record.dart';

/// Displays basic patient info at the top of clinical action screens.
class PatientInfoCard extends StatelessWidget {
  final PatientRecord patientRecord;

  const PatientInfoCard({super.key, required this.patientRecord});

  @override
  Widget build(BuildContext context) {
    final patient = patientRecord.patient;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              patient?.fullName ?? 'Unknown Patient',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 4),
            Text('No: ${patient?.patientNumber ?? 'N/A'}',
                style: TextStyle(color: Colors.grey[600])),
            if (patient?.chiefComplaint != null) ...[
              const SizedBox(height: 4),
              Text('Complaint: ${patient!.chiefComplaint}',
                  style: const TextStyle(fontSize: 13)),
            ],
          ],
        ),
      ),
    );
  }
}

/// Orange warning banner shown when the API response contains a `warning` field.
/// Requirements: 8.4, 9.5
class WarningBanner extends StatelessWidget {
  final String message;

  const WarningBanner({super.key, required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.orange.shade100,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.orange.shade400, width: 1.5),
      ),
      child: Row(
        children: [
          Icon(Icons.warning_amber_rounded,
              color: Colors.orange.shade800, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              message,
              style: TextStyle(
                color: Colors.orange.shade900,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
