import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hhms_mobile/domain/repositories/department_repository.dart';
import 'package:hhms_mobile/presentation/screens/patient_detail_screen.dart';

// Helper to build a JourneyEvent with optional metadata
JourneyEvent _makeEvent({Map<String, dynamic>? metadata}) {
  return JourneyEvent(
    id: 'evt-1',
    patientRecordId: 'rec-1',
    eventType: 'note_added',
    performedBy: 'user-1',
    createdAt: DateTime(2024, 4, 22, 10, 0),
    metadata: metadata,
  );
}

// Wraps a widget in a minimal MaterialApp for testing
Widget _wrap(Widget child) => MaterialApp(home: Scaffold(body: child));

void main() {
  // ─── Property 5: card renders present clinical metadata fields ───────────
  // Validates: Requirements 5.1, 5.2
  group(
    'Property 5: journey event card renders present clinical metadata fields',
    () {
      // A representative set of non-empty, non-whitespace strings
      const sampleTexts = [
        '3-day history of fever and cough',
        'Headache for 2 days',
        'Chest pain radiating to left arm',
        'BP 120/80, HR 72 bpm',
        'Temperature 38.5°C, bilateral crepitations',
        'a',
        'multi\nline\ntext',
        '  leading and trailing spaces  ',
      ];

      for (final text in sampleTexts) {
        testWidgets(
          'renders complaint_history section when value is "$text"',
          (tester) async {
            final event = _makeEvent(metadata: {'complaint_history': text});
            await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

            expect(find.text('Complaint & History'), findsOneWidget);
            expect(find.text(text), findsOneWidget);
          },
        );

        testWidgets(
          'renders examination_findings section when value is "$text"',
          (tester) async {
            final event =
                _makeEvent(metadata: {'examination_findings': text});
            await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

            expect(find.text('Examination Findings'), findsOneWidget);
            expect(find.text(text), findsOneWidget);
          },
        );
      }

      testWidgets('renders both sections when both metadata fields are present',
          (tester) async {
        final event = _makeEvent(metadata: {
          'complaint_history': 'Fever for 3 days',
          'examination_findings': 'Temp 38.5°C',
        });
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Complaint & History'), findsOneWidget);
        expect(find.text('Fever for 3 days'), findsOneWidget);
        expect(find.text('Examination Findings'), findsOneWidget);
        expect(find.text('Temp 38.5°C'), findsOneWidget);
      });
    },
  );

  // ─── Property 7: card does not render absent clinical metadata sections ───
  // Validates: Requirement 5.3
  group(
    'Property 7: journey event card does not render absent clinical metadata sections',
    () {
      testWidgets('renders no clinical sections when metadata is null',
          (tester) async {
        final event = _makeEvent(metadata: null);
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Complaint & History'), findsNothing);
        expect(find.text('Examination Findings'), findsNothing);
      });

      testWidgets('renders no clinical sections when metadata is empty map',
          (tester) async {
        final event = _makeEvent(metadata: {});
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Complaint & History'), findsNothing);
        expect(find.text('Examination Findings'), findsNothing);
      });

      testWidgets(
          'renders no complaint section when complaint_history is empty string',
          (tester) async {
        final event = _makeEvent(metadata: {'complaint_history': ''});
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Complaint & History'), findsNothing);
      });

      testWidgets(
          'renders no examination section when examination_findings is empty string',
          (tester) async {
        final event = _makeEvent(metadata: {'examination_findings': ''});
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Examination Findings'), findsNothing);
      });

      testWidgets(
          'renders no complaint section when complaint_history is null in metadata',
          (tester) async {
        final event =
            _makeEvent(metadata: {'complaint_history': null});
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Complaint & History'), findsNothing);
      });

      testWidgets(
          'renders no examination section when examination_findings is null in metadata',
          (tester) async {
        final event =
            _makeEvent(metadata: {'examination_findings': null});
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Examination Findings'), findsNothing);
      });

      testWidgets(
          'renders only examination section when only examination_findings is present',
          (tester) async {
        final event = _makeEvent(
            metadata: {'examination_findings': 'BP 120/80'});
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Complaint & History'), findsNothing);
        expect(find.text('Examination Findings'), findsOneWidget);
      });

      testWidgets(
          'renders only complaint section when only complaint_history is present',
          (tester) async {
        final event =
            _makeEvent(metadata: {'complaint_history': 'Headache'});
        await tester.pumpWidget(_wrap(JourneyEventCardTestable(event: event)));

        expect(find.text('Complaint & History'), findsOneWidget);
        expect(find.text('Examination Findings'), findsNothing);
      });
    },
  );
}
