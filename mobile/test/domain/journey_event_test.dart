import 'package:flutter_test/flutter_test.dart';
import 'package:hhms_mobile/domain/repositories/department_repository.dart';

void main() {
  group('JourneyEvent.fromJson', () {
    // Base JSON with required fields
    Map<String, dynamic> baseJson({Map<String, dynamic>? metadata}) => {
          'id': 'event-123',
          'patient_record_id': 'record-456',
          'from_department_id': null,
          'to_department_id': null,
          'from_department': null,
          'to_department': null,
          'event_type': 'note_added',
          'performed_by': 'user-789',
          'performed_by_user': null,
          'notes': 'Some notes',
          'metadata': metadata,
          'created_at': '2024-04-22T10:00:00.000Z',
        };

    test('parses metadata with complaint_history and examination_findings', () {
      final json = baseJson(metadata: {
        'complaint_history': '3-day history of fever and cough',
        'examination_findings': 'Temperature 38.5°C, bilateral crepitations',
      });

      final event = JourneyEvent.fromJson(json);

      expect(event.metadata, isNotNull);
      expect(event.metadata!['complaint_history'],
          equals('3-day history of fever and cough'));
      expect(event.metadata!['examination_findings'],
          equals('Temperature 38.5°C, bilateral crepitations'));
    });

    test('metadata is null when key is absent from JSON', () {
      final Map<String, dynamic> json = {
        'id': 'event-123',
        'patient_record_id': 'record-456',
        'from_department_id': null,
        'to_department_id': null,
        'from_department': null,
        'to_department': null,
        'event_type': 'note_added',
        'performed_by': 'user-789',
        'performed_by_user': null,
        'notes': 'Some notes',
        // 'metadata' key intentionally absent
        'created_at': '2024-04-22T10:00:00.000Z',
      };

      final event = JourneyEvent.fromJson(json);

      expect(event.metadata, isNull);
    });

    test('metadata is null when key is explicitly null in JSON', () {
      final json = baseJson(metadata: null);

      final event = JourneyEvent.fromJson(json);

      expect(event.metadata, isNull);
    });

    test('parses metadata with only complaint_history present', () {
      final json = baseJson(metadata: {
        'complaint_history': 'Headache for 2 days',
      });

      final event = JourneyEvent.fromJson(json);

      expect(event.metadata, isNotNull);
      expect(event.metadata!['complaint_history'], equals('Headache for 2 days'));
      expect(event.metadata!.containsKey('examination_findings'), isFalse);
    });

    test('parses metadata with only examination_findings present', () {
      final json = baseJson(metadata: {
        'examination_findings': 'BP 120/80, HR 72 bpm',
      });

      final event = JourneyEvent.fromJson(json);

      expect(event.metadata, isNotNull);
      expect(event.metadata!['examination_findings'], equals('BP 120/80, HR 72 bpm'));
      expect(event.metadata!.containsKey('complaint_history'), isFalse);
    });

    test('parses metadata with additional existing keys', () {
      final json = baseJson(metadata: {
        'complaint_history': 'Chest pain',
        'examination_findings': 'Tachycardia',
        'prescription_notes': 'Aspirin 75mg',
        'investigation_notes': 'ECG requested',
      });

      final event = JourneyEvent.fromJson(json);

      expect(event.metadata, isNotNull);
      expect(event.metadata!['complaint_history'], equals('Chest pain'));
      expect(event.metadata!['examination_findings'], equals('Tachycardia'));
      expect(event.metadata!['prescription_notes'], equals('Aspirin 75mg'));
      expect(event.metadata!['investigation_notes'], equals('ECG requested'));
    });

    test('parses all required fields correctly alongside metadata', () {
      final json = baseJson(metadata: {
        'complaint_history': 'Fever',
      });

      final event = JourneyEvent.fromJson(json);

      expect(event.id, equals('event-123'));
      expect(event.patientRecordId, equals('record-456'));
      expect(event.eventType, equals('note_added'));
      expect(event.performedBy, equals('user-789'));
      expect(event.notes, equals('Some notes'));
      expect(event.createdAt, equals(DateTime.parse('2024-04-22T10:00:00.000Z')));
      expect(event.metadata!['complaint_history'], equals('Fever'));
    });
  });
}
