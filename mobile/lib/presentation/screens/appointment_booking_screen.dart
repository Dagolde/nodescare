import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/clinical_provider.dart';
import '../providers/accounts_provider.dart';

/// Doctor screen for booking a follow-up appointment.
/// Requirements: 7.7, 7.8, 7.9
class AppointmentBookingScreen extends ConsumerStatefulWidget {
  /// Optional patient record ID to link the appointment to a visit.
  final String? patientRecordId;

  const AppointmentBookingScreen({super.key, this.patientRecordId});

  @override
  ConsumerState<AppointmentBookingScreen> createState() =>
      _AppointmentBookingScreenState();
}

class _AppointmentBookingScreenState
    extends ConsumerState<AppointmentBookingScreen> {
  final _formKey = GlobalKey<FormState>();
  final _patientNumberController = TextEditingController();
  final _notesController = TextEditingController();

  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;

  // Resolved patient ID after search
  String? _resolvedPatientId;
  bool _isSearching = false;
  String? _searchError;
  String? _foundPatientName;

  @override
  void dispose() {
    _patientNumberController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now.add(const Duration(days: 1)),
      firstDate: now.add(const Duration(days: 1)), // Req 7.9: future only
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _selectedDate = picked);
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (picked != null) setState(() => _selectedTime = picked);
  }

  DateTime? get _scheduledAt {
    if (_selectedDate == null || _selectedTime == null) return null;
    return DateTime(
      _selectedDate!.year,
      _selectedDate!.month,
      _selectedDate!.day,
      _selectedTime!.hour,
      _selectedTime!.minute,
    );
  }

  /// Simulates patient lookup by patient number via the repository.
  /// In a real implementation this would call GET /api/v1/patients?patient_number=...
  Future<void> _searchPatient() async {
    final number = _patientNumberController.text.trim();
    if (number.isEmpty) return;

    setState(() {
      _isSearching = true;
      _searchError = null;
      _resolvedPatientId = null;
      _foundPatientName = null;
    });

    try {
      // Call the patient search endpoint
      final dataSource = ref.read(remoteDataSourceProvider);
      final response = await dataSource.get('/api/v1/patients/search?q=$number');
      
      if (response.data['success'] == true) {
        final List<dynamic> results = response.data['data'];
        
        if (results.isEmpty) {
          setState(() {
            _isSearching = false;
            _searchError = 'No patient found with that number';
          });
          return;
        }
        
        // Find exact match by patient number
        final exactMatch = results.firstWhere(
          (p) => p['patient_number'] == number,
          orElse: () => results.first,
        );
        
        setState(() {
          _isSearching = false;
          _resolvedPatientId = exactMatch['id'];
          _foundPatientName = '${exactMatch['full_name']} (${exactMatch['patient_number']})';
        });
      } else {
        setState(() {
          _isSearching = false;
          _searchError = 'Failed to search for patient';
        });
      }
    } catch (e) {
      setState(() {
        _isSearching = false;
        _searchError = 'Error searching for patient: ${e.toString()}';
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    if (_resolvedPatientId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please search for a patient first.')),
      );
      return;
    }

    if (_scheduledAt == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a date and time.')),
      );
      return;
    }

    await ref.read(appointmentBookingProvider.notifier).bookAppointment(
          patientId: _resolvedPatientId!,
          scheduledAt: _scheduledAt!,
          notes: _notesController.text.trim().isEmpty
              ? null
              : _notesController.text.trim(),
        );
  }

  @override
  Widget build(BuildContext context) {
    final bookingState = ref.watch(appointmentBookingProvider);

    // Listen for success/error
    ref.listen<AsyncValue>(appointmentBookingProvider, (_, next) {
      next.whenOrNull(
        data: (result) {
          if (result != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Appointment booked successfully'),
                backgroundColor: Colors.green,
              ),
            );
            ref.read(appointmentBookingProvider.notifier).reset();
            Navigator.of(context).pop(true);
          }
        },
        error: (e, _) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(e.toString().replaceFirst('Exception: ', '')),
              backgroundColor: Colors.red,
            ),
          );
        },
      );
    });

    final isLoading = bookingState is AsyncLoading;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Book Appointment'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // ── Patient Search ──────────────────────────────────────────
              const Text(
                'Patient',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _patientNumberController,
                      decoration: const InputDecoration(
                        labelText: 'Patient Number',
                        hintText: 'e.g. HOS-2024-00001',
                        border: OutlineInputBorder(),
                        prefixIcon: Icon(Icons.person_search),
                      ),
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? 'Required' : null,
                    ),
                  ),
                  const SizedBox(width: 8),
                  ElevatedButton(
                    onPressed: _isSearching ? null : _searchPatient,
                    child: _isSearching
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Search'),
                  ),
                ],
              ),
              if (_searchError != null) ...[
                const SizedBox(height: 4),
                Text(_searchError!, style: const TextStyle(color: Colors.red)),
              ],
              if (_foundPatientName != null) ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.green.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.green.shade200),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.check_circle, color: Colors.green),
                      const SizedBox(width: 8),
                      Text(
                        _foundPatientName!,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 24),

              // ── Date & Time ─────────────────────────────────────────────
              const Text(
                'Appointment Date & Time',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.calendar_today),
                      label: Text(
                        _selectedDate == null
                            ? 'Select Date'
                            : '${_selectedDate!.day}/${_selectedDate!.month}/${_selectedDate!.year}',
                      ),
                      onPressed: _pickDate,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.access_time),
                      label: Text(
                        _selectedTime == null
                            ? 'Select Time'
                            : _selectedTime!.format(context),
                      ),
                      onPressed: _pickTime,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // ── Notes ───────────────────────────────────────────────────
              const Text(
                'Notes (optional)',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _notesController,
                maxLines: 4,
                decoration: const InputDecoration(
                  hintText: 'Add any relevant notes for this appointment...',
                  border: OutlineInputBorder(),
                ),
              ),

              const SizedBox(height: 32),

              // ── Submit ──────────────────────────────────────────────────
              ElevatedButton(
                onPressed: isLoading ? null : _submit,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  foregroundColor: Colors.white,
                ),
                child: isLoading
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text(
                        'Book Appointment',
                        style: TextStyle(fontSize: 16),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
