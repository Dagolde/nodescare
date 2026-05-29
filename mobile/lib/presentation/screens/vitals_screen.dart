import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/patient_record.dart';
import '../providers/accounts_provider.dart';
import '../providers/department_provider.dart';

class VitalsScreen extends ConsumerStatefulWidget {
  final PatientRecord patientRecord;

  const VitalsScreen({super.key, required this.patientRecord});

  @override
  ConsumerState<VitalsScreen> createState() => _VitalsScreenState();
}

class _VitalsScreenState extends ConsumerState<VitalsScreen> {
  final _formKey = GlobalKey<FormState>();
  bool _isSaving = false;
  bool _isRouting = false;

  final _bpController = TextEditingController();
  final _tempController = TextEditingController();
  final _pulseController = TextEditingController();
  final _rrController = TextEditingController();
  final _spo2Controller = TextEditingController();
  final _weightController = TextEditingController();
  final _heightController = TextEditingController();
  final _glucoseController = TextEditingController();
  final _notesController = TextEditingController();

  @override
  void dispose() {
    _bpController.dispose();
    _tempController.dispose();
    _pulseController.dispose();
    _rrController.dispose();
    _spo2Controller.dispose();
    _weightController.dispose();
    _heightController.dispose();
    _glucoseController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Map<String, dynamic> _buildPayload() {
    final data = <String, dynamic>{};
    if (_bpController.text.isNotEmpty) data['blood_pressure'] = _bpController.text.trim();
    if (_tempController.text.isNotEmpty) data['temperature'] = double.tryParse(_tempController.text);
    if (_pulseController.text.isNotEmpty) data['pulse_rate'] = int.tryParse(_pulseController.text);
    if (_rrController.text.isNotEmpty) data['respiratory_rate'] = int.tryParse(_rrController.text);
    if (_spo2Controller.text.isNotEmpty) data['oxygen_saturation'] = int.tryParse(_spo2Controller.text);
    if (_weightController.text.isNotEmpty) data['weight'] = double.tryParse(_weightController.text);
    if (_heightController.text.isNotEmpty) data['height'] = double.tryParse(_heightController.text);
    if (_glucoseController.text.isNotEmpty) data['blood_glucose'] = int.tryParse(_glucoseController.text);
    if (_notesController.text.isNotEmpty) data['notes'] = _notesController.text.trim();
    return data;
  }

  bool get _hasAnyValue =>
      _bpController.text.isNotEmpty ||
      _tempController.text.isNotEmpty ||
      _pulseController.text.isNotEmpty ||
      _rrController.text.isNotEmpty ||
      _spo2Controller.text.isNotEmpty ||
      _weightController.text.isNotEmpty ||
      _heightController.text.isNotEmpty ||
      _glucoseController.text.isNotEmpty ||
      _notesController.text.isNotEmpty;

  Future<void> _saveVitals() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_hasAnyValue) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter at least one vital sign'), backgroundColor: Colors.orange),
      );
      return;
    }

    setState(() => _isSaving = true);
    try {
      final ds = ref.read(remoteDataSourceProvider);
      await ds.post('/api/v1/patient-records/${widget.patientRecord.id}/vitals', data: _buildPayload());
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Vitals saved successfully'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save vitals: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  Future<void> _saveAndRoute() async {
    if (!_formKey.currentState!.validate()) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Route to Doctor'),
        content: const Text('Save vitals and route this patient to Doctor Consultation?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.teal),
            child: const Text('Confirm', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _isRouting = true);
    try {
      final ds = ref.read(remoteDataSourceProvider);

      // Save vitals first (if any entered)
      if (_hasAnyValue) {
        await ds.post('/api/v1/patient-records/${widget.patientRecord.id}/vitals', data: _buildPayload());
      }

      // Route to next department (Doctor)
      await ds.post('/api/v1/patient-records/${widget.patientRecord.id}/complete', data: {
        'notes': _notesController.text.isNotEmpty
            ? _notesController.text.trim()
            : 'Vitals assessed. Routing to Doctor.',
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Patient routed to Doctor Consultation'),
            backgroundColor: Colors.green,
            duration: Duration(seconds: 3),
          ),
        );
        // Refresh queue and go back
        ref.read(departmentQueueNotifierProvider.notifier).refresh();
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isRouting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final patient = widget.patientRecord.patient;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Record Vitals'),
        backgroundColor: Colors.teal,
        foregroundColor: Colors.white,
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Patient info card
              Card(
                color: Colors.teal.shade50,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: Colors.teal,
                        child: Text(
                          patient?.fullName.isNotEmpty == true ? patient!.fullName[0].toUpperCase() : 'P',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(patient?.fullName ?? 'Unknown', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                            Text('No: ${patient?.patientNumber ?? 'N/A'}', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                            if (patient?.chiefComplaint.isNotEmpty == true)
                              Text('CC: ${patient!.chiefComplaint}', style: const TextStyle(fontSize: 13)),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              const Text('Vital Signs', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              // BP and Temperature
              Row(
                children: [
                  Expanded(
                    child: _VitalField(
                      controller: _bpController,
                      label: 'Blood Pressure',
                      hint: '120/80',
                      unit: 'mmHg',
                      icon: Icons.favorite,
                      iconColor: Colors.red,
                      keyboardType: TextInputType.text,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _VitalField(
                      controller: _tempController,
                      label: 'Temperature',
                      hint: '37.0',
                      unit: '°C',
                      icon: Icons.thermostat,
                      iconColor: Colors.orange,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      validator: (v) {
                        if (v == null || v.isEmpty) return null;
                        final d = double.tryParse(v);
                        if (d == null || d < 30 || d > 45) return '30–45°C';
                        return null;
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Pulse and SpO2
              Row(
                children: [
                  Expanded(
                    child: _VitalField(
                      controller: _pulseController,
                      label: 'Pulse Rate',
                      hint: '72',
                      unit: 'bpm',
                      icon: Icons.monitor_heart,
                      iconColor: Colors.pink,
                      keyboardType: TextInputType.number,
                      validator: (v) {
                        if (v == null || v.isEmpty) return null;
                        final i = int.tryParse(v);
                        if (i == null || i < 20 || i > 300) return '20–300';
                        return null;
                      },
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _VitalField(
                      controller: _spo2Controller,
                      label: 'SpO2',
                      hint: '98',
                      unit: '%',
                      icon: Icons.air,
                      iconColor: Colors.blue,
                      keyboardType: TextInputType.number,
                      validator: (v) {
                        if (v == null || v.isEmpty) return null;
                        final i = int.tryParse(v);
                        if (i == null || i < 50 || i > 100) return '50–100%';
                        return null;
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Respiratory Rate and Blood Glucose
              Row(
                children: [
                  Expanded(
                    child: _VitalField(
                      controller: _rrController,
                      label: 'Resp. Rate',
                      hint: '16',
                      unit: '/min',
                      icon: Icons.wind_power,
                      iconColor: Colors.cyan,
                      keyboardType: TextInputType.number,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _VitalField(
                      controller: _glucoseController,
                      label: 'Blood Glucose',
                      hint: '90',
                      unit: 'mg/dL',
                      icon: Icons.water_drop,
                      iconColor: Colors.amber,
                      keyboardType: TextInputType.number,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Weight and Height
              Row(
                children: [
                  Expanded(
                    child: _VitalField(
                      controller: _weightController,
                      label: 'Weight',
                      hint: '70',
                      unit: 'kg',
                      icon: Icons.monitor_weight,
                      iconColor: Colors.brown,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _VitalField(
                      controller: _heightController,
                      label: 'Height',
                      hint: '170',
                      unit: 'cm',
                      icon: Icons.height,
                      iconColor: Colors.indigo,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Notes
              TextFormField(
                controller: _notesController,
                decoration: InputDecoration(
                  labelText: 'Clinical Notes',
                  hintText: 'Additional observations, allergies, etc.',
                  border: const OutlineInputBorder(),
                  prefixIcon: const Icon(Icons.notes, color: Colors.grey),
                ),
                maxLines: 3,
              ),
              const SizedBox(height: 24),

              // Action buttons
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: _isSaving || _isRouting ? null : _saveVitals,
                      icon: _isSaving
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.save),
                      label: const Text('Save Vitals'),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        side: const BorderSide(color: Colors.teal),
                        foregroundColor: Colors.teal,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    flex: 2,
                    child: ElevatedButton.icon(
                      onPressed: _isSaving || _isRouting ? null : _saveAndRoute,
                      icon: _isRouting
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.arrow_forward),
                      label: const Text('Save & Route to Doctor'),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        backgroundColor: Colors.teal,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}

class _VitalField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final String hint;
  final String unit;
  final IconData icon;
  final Color iconColor;
  final TextInputType keyboardType;
  final String? Function(String?)? validator;

  const _VitalField({
    required this.controller,
    required this.label,
    required this.hint,
    required this.unit,
    required this.icon,
    required this.iconColor,
    required this.keyboardType,
    this.validator,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      validator: validator,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        suffixText: unit,
        border: const OutlineInputBorder(),
        prefixIcon: Icon(icon, color: iconColor, size: 20),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      ),
    );
  }
}
