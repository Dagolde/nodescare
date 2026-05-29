import 'dart:io';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../domain/entities/doctor_summary.dart';
import '../../domain/repositories/patient_repository.dart';
import '../providers/patient_provider.dart';
import '../providers/visit_type_provider.dart';

/// Maximum allowed document size: 10 MB
const int _maxDocBytes = 10 * 1024 * 1024;

class PatientRegistrationScreen extends ConsumerStatefulWidget {
  const PatientRegistrationScreen({super.key});

  @override
  ConsumerState<PatientRegistrationScreen> createState() =>
      _PatientRegistrationScreenState();
}

class _PatientRegistrationScreenState
    extends ConsumerState<PatientRegistrationScreen> {
  final _formKey = GlobalKey<FormState>();

  // Controllers
  final _fullNameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _nokNameCtrl = TextEditingController();
  final _nokPhoneCtrl = TextEditingController();
  final _chiefComplaintCtrl = TextEditingController();
  final _hmoProviderCtrl = TextEditingController();
  final _hmoPolicyCtrl = TextEditingController();

  // State
  DateTime? _dateOfBirth;
  String? _gender;
  File? _photo;
  final List<File> _documents = [];
  String? _docSizeError;

  // Visit type & doctor assignment
  String? _selectedVisitTypeSlug;
  String? _selectedDoctorId;
  List<DoctorSummary> _availableDoctors = [];
  bool _loadingDoctors = false;
  bool _noDoctorsAvailable = false;

  static const _genders = ['male', 'female', 'other'];

  @override
  void dispose() {
    _fullNameCtrl.dispose();
    _phoneCtrl.dispose();
    _addressCtrl.dispose();
    _nokNameCtrl.dispose();
    _nokPhoneCtrl.dispose();
    _chiefComplaintCtrl.dispose();
    _hmoProviderCtrl.dispose();
    _hmoPolicyCtrl.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    // Load visit types after first frame using ref.watch in build instead
  }

  Future<void> _onVisitTypeChanged(String? slug) async {
    setState(() {
      _selectedVisitTypeSlug = slug;
      _selectedDoctorId = null;
      _availableDoctors = [];
      _noDoctorsAvailable = false;
    });
    if (slug == null) return;
    setState(() => _loadingDoctors = true);
    try {
      // Invalidate cache to always get fresh results from server
      ref.invalidate(doctorsByVisitTypeProvider(slug));
      final doctors = await ref.read(doctorsByVisitTypeProvider(slug).future);
      if (mounted) {
        setState(() {
          _availableDoctors = doctors;
          _noDoctorsAvailable = doctors.isEmpty;
          _loadingDoctors = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingDoctors = false);
    }
  }

  // ── Photo picker ────────────────────────────────────────────────────────

  Future<void> _pickPhoto(ImageSource source) async {
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: source, imageQuality: 85);
    if (picked != null) {
      setState(() => _photo = File(picked.path));
    }
  }

  void _showPhotoOptions() {
    showModalBottomSheet<void>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Take photo'),
              onTap: () {
                Navigator.pop(context);
                _pickPhoto(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Choose from gallery'),
              onTap: () {
                Navigator.pop(context);
                _pickPhoto(ImageSource.gallery);
              },
            ),
          ],
        ),
      ),
    );
  }

  // ── Document picker ─────────────────────────────────────────────────────

  Future<void> _pickDocument() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      allowMultiple: true,
    );
    if (result == null) return;

    setState(() => _docSizeError = null);

    for (final pf in result.files) {
      if (pf.path == null) continue;
      final file = File(pf.path!);
      final size = await file.length();
      if (size > _maxDocBytes) {
        setState(() => _docSizeError =
            '"${pf.name}" exceeds the 10 MB limit and was not added.');
        continue;
      }
      setState(() => _documents.add(file));
    }
  }

  void _removeDocument(int index) {
    setState(() => _documents.removeAt(index));
  }

  // ── Date picker ─────────────────────────────────────────────────────────

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dateOfBirth ?? DateTime(now.year - 30),
      firstDate: DateTime(1900),
      lastDate: now,
    );
    if (picked != null) setState(() => _dateOfBirth = picked);
  }

  // ── Submit ───────────────────────────────────────────────────────────────

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_dateOfBirth == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a date of birth')),
      );
      return;
    }
    if (_gender == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a gender')),
      );
      return;
    }

    final data = PatientRegistrationData(
      fullName: _fullNameCtrl.text.trim(),
      dateOfBirth: _dateOfBirth!,
      gender: _gender!,
      phone: _phoneCtrl.text.trim(),
      address: _addressCtrl.text.trim().isEmpty ? null : _addressCtrl.text.trim(),
      nextOfKinName: _nokNameCtrl.text.trim().isEmpty ? null : _nokNameCtrl.text.trim(),
      nextOfKinPhone: _nokPhoneCtrl.text.trim().isEmpty ? null : _nokPhoneCtrl.text.trim(),
      chiefComplaint: _chiefComplaintCtrl.text.trim(),
      hmoProviderName: _hmoProviderCtrl.text.trim().isEmpty ? null : _hmoProviderCtrl.text.trim(),
      hmoPolicyNumber: _hmoPolicyCtrl.text.trim().isEmpty ? null : _hmoPolicyCtrl.text.trim(),
      photo: _photo,
      documents: List.unmodifiable(_documents),
      visitType: _selectedVisitTypeSlug,
      assignedDoctorId: _selectedDoctorId,
    );

    await ref.read(patientRegistrationProvider.notifier).register(data);
  }

  // ── Build ────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final registrationState = ref.watch(patientRegistrationProvider);
    final isOnline = ref.watch(isOnlineProvider);

    // React to successful registration
    ref.listen<AsyncValue<dynamic>>(patientRegistrationProvider, (_, next) {
      next.when(
        data: (record) {
          if (record == null) return;
          final isQueued = record.id.startsWith('local_');
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(isQueued
                  ? 'Registration queued — will sync when online.'
                  : 'Patient registered successfully.'),
              backgroundColor: isQueued ? Colors.orange : Colors.green,
            ),
          );
          if (!isQueued) {
            ref.read(patientRegistrationProvider.notifier).reset();
            Navigator.of(context).pop();
          }
        },
        loading: () {},
        error: (e, _) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Registration failed: $e'),
              backgroundColor: Colors.red,
              duration: const Duration(seconds: 6),
            ),
          );
        },
      );
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Register Patient'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: Column(
        children: [
          if (!isOnline) _OfflineBanner(),
          Expanded(
            child: Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _SectionHeader('Patient Details'),
                  _buildFullName(),
                  const SizedBox(height: 12),
                  _buildDob(),
                  const SizedBox(height: 12),
                  _buildGender(),
                  const SizedBox(height: 12),
                  _buildPhone(),
                  const SizedBox(height: 12),
                  _buildAddress(),
                  const SizedBox(height: 20),
                  _SectionHeader('Next of Kin'),
                  _buildNokName(),
                  const SizedBox(height: 12),
                  _buildNokPhone(),
                  const SizedBox(height: 20),
                  _SectionHeader('Clinical'),
                  _buildChiefComplaint(),
                  const SizedBox(height: 20),
                  _SectionHeader('Visit Type & Doctor Assignment'),
                  _buildVisitTypeDropdown(),
                  const SizedBox(height: 12),
                  _buildDoctorPicker(),
                  const SizedBox(height: 20),
                  _SectionHeader('Insurance / HMO'),
                  _buildHmoProvider(),
                  const SizedBox(height: 12),
                  _buildHmoPolicy(),
                  const SizedBox(height: 20),
                  _SectionHeader('Photograph'),
                  _buildPhotoSection(),
                  const SizedBox(height: 20),
                  _SectionHeader('Documents'),
                  _buildDocumentsSection(),
                  const SizedBox(height: 32),
                  _buildSubmitButton(registrationState),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ── Form fields ──────────────────────────────────────────────────────────

  Widget _buildFullName() => TextFormField(
        controller: _fullNameCtrl,
        decoration: const InputDecoration(
          labelText: 'Full Name *',
          border: OutlineInputBorder(),
        ),
        textCapitalization: TextCapitalization.words,
        validator: (v) =>
            (v == null || v.trim().isEmpty) ? 'Full name is required' : null,
      );

  Widget _buildDob() => InkWell(
        onTap: _pickDate,
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: 'Date of Birth *',
            border: const OutlineInputBorder(),
            errorText: _dateOfBirth == null ? null : null,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                _dateOfBirth == null
                    ? 'Select date'
                    : '${_dateOfBirth!.day}/${_dateOfBirth!.month}/${_dateOfBirth!.year}',
                style: TextStyle(
                  color: _dateOfBirth == null ? Colors.grey : null,
                ),
              ),
              const Icon(Icons.calendar_today, size: 18),
            ],
          ),
        ),
      );

  Widget _buildGender() => DropdownButtonFormField<String>(
        value: _gender,
        decoration: const InputDecoration(
          labelText: 'Gender *',
          border: OutlineInputBorder(),
        ),
        items: _genders
            .map((g) => DropdownMenuItem(
                  value: g,
                  child: Text(g[0].toUpperCase() + g.substring(1)),
                ))
            .toList(),
        onChanged: (v) => setState(() => _gender = v),
        validator: (v) => v == null ? 'Gender is required' : null,
      );

  Widget _buildPhone() => TextFormField(
        controller: _phoneCtrl,
        decoration: const InputDecoration(
          labelText: 'Phone Number *',
          border: OutlineInputBorder(),
        ),
        keyboardType: TextInputType.phone,
        validator: (v) =>
            (v == null || v.trim().isEmpty) ? 'Phone number is required' : null,
      );

  Widget _buildAddress() => TextFormField(
        controller: _addressCtrl,
        decoration: const InputDecoration(
          labelText: 'Residential Address',
          border: OutlineInputBorder(),
        ),
        maxLines: 2,
      );

  Widget _buildNokName() => TextFormField(
        controller: _nokNameCtrl,
        decoration: const InputDecoration(
          labelText: 'Next-of-Kin Name',
          border: OutlineInputBorder(),
        ),
        textCapitalization: TextCapitalization.words,
      );

  Widget _buildNokPhone() => TextFormField(
        controller: _nokPhoneCtrl,
        decoration: const InputDecoration(
          labelText: 'Next-of-Kin Phone',
          border: OutlineInputBorder(),
        ),
        keyboardType: TextInputType.phone,
      );

  Widget _buildChiefComplaint() => TextFormField(
        controller: _chiefComplaintCtrl,
        decoration: const InputDecoration(
          labelText: 'Chief Complaint *',
          border: OutlineInputBorder(),
          alignLabelWithHint: true,
        ),
        maxLines: 4,
        validator: (v) =>
            (v == null || v.trim().isEmpty) ? 'Chief complaint is required' : null,
      );

  Widget _buildVisitTypeDropdown() {
    final visitTypesAsync = ref.watch(visitTypesProvider);
    return visitTypesAsync.when(
      data: (types) => DropdownButtonFormField<String>(
        value: _selectedVisitTypeSlug,
        decoration: const InputDecoration(
          labelText: 'Visit Type *',
          border: OutlineInputBorder(),
          prefixIcon: Icon(Icons.category),
        ),
        items: types
            .map((vt) => DropdownMenuItem(value: vt.slug, child: Text(vt.name)))
            .toList(),
        onChanged: _onVisitTypeChanged,
        validator: (v) => v == null ? 'Visit type is required' : null,
      ),
      loading: () => const InputDecorator(
        decoration: InputDecoration(
          labelText: 'Visit Type *',
          border: OutlineInputBorder(),
        ),
        child: Row(
          children: [
            SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
            SizedBox(width: 8),
            Text('Loading visit types...', style: TextStyle(color: Colors.grey)),
          ],
        ),
      ),
      error: (e, _) => InputDecorator(
        decoration: const InputDecoration(
          labelText: 'Visit Type *',
          border: OutlineInputBorder(),
          errorText: 'Failed to load visit types',
        ),
        child: Text(e.toString(), style: const TextStyle(fontSize: 12, color: Colors.red)),
      ),
    );
  }

  Widget _buildDoctorPicker() {
    if (_selectedVisitTypeSlug == null) {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.grey.shade100,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.grey.shade300),
        ),
        child: const Row(
          children: [
            Icon(Icons.info_outline, size: 18, color: Colors.grey),
            SizedBox(width: 8),
            Expanded(
              child: Text(
                'Select a visit type first to see available doctors',
                style: TextStyle(fontSize: 13, color: Colors.grey),
              ),
            ),
          ],
        ),
      );
    }

    if (_loadingDoctors) {
      return Container(
        padding: const EdgeInsets.all(16),
        child: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_noDoctorsAvailable) {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.orange.shade50,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: Colors.orange.shade300),
        ),
        child: const Row(
          children: [
            Icon(Icons.warning_amber, size: 18, color: Colors.orange),
            SizedBox(width: 8),
            Expanded(
              child: Text(
                'No doctors available for this visit type. You may proceed without assigning a doctor.',
                style: TextStyle(fontSize: 13),
              ),
            ),
          ],
        ),
      );
    }

    return DropdownButtonFormField<String>(
      value: _selectedDoctorId,
      decoration: const InputDecoration(
        labelText: 'Assigned Doctor *',
        border: OutlineInputBorder(),
        prefixIcon: Icon(Icons.person),
      ),
      items: _availableDoctors
          .map((doc) => DropdownMenuItem(
                value: doc.id,
                child: Text('${doc.name} (${doc.departmentName ?? 'N/A'})'),
              ))
          .toList(),
      onChanged: (v) => setState(() => _selectedDoctorId = v),
      validator: (v) => _availableDoctors.isNotEmpty && v == null
          ? 'Please select a doctor'
          : null,
    );
  }

  Widget _buildHmoProvider() => TextFormField(
        controller: _hmoProviderCtrl,
        decoration: const InputDecoration(
          labelText: 'HMO Provider Name',
          border: OutlineInputBorder(),
        ),
      );

  Widget _buildHmoPolicy() => TextFormField(
        controller: _hmoPolicyCtrl,
        decoration: const InputDecoration(
          labelText: 'HMO Policy / Member Number',
          border: OutlineInputBorder(),
        ),
      );

  // ── Photo section ────────────────────────────────────────────────────────

  Widget _buildPhotoSection() => Row(
        children: [
          GestureDetector(
            onTap: _showPhotoOptions,
            child: Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.grey),
                borderRadius: BorderRadius.circular(8),
              ),
              child: _photo != null
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.file(_photo!, fit: BoxFit.cover),
                    )
                  : const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.add_a_photo, size: 32, color: Colors.grey),
                        SizedBox(height: 4),
                        Text('Add photo',
                            style: TextStyle(fontSize: 11, color: Colors.grey)),
                      ],
                    ),
            ),
          ),
          const SizedBox(width: 12),
          if (_photo != null)
            TextButton.icon(
              onPressed: () => setState(() => _photo = null),
              icon: const Icon(Icons.delete, color: Colors.red),
              label: const Text('Remove', style: TextStyle(color: Colors.red)),
            ),
        ],
      );

  // ── Documents section ────────────────────────────────────────────────────

  Widget _buildDocumentsSection() => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_docSizeError != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(_docSizeError!,
                  style: const TextStyle(color: Colors.red, fontSize: 12)),
            ),
          ..._documents.asMap().entries.map((e) => ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.insert_drive_file),
                title: Text(
                  e.value.path.split('/').last,
                  overflow: TextOverflow.ellipsis,
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.close, color: Colors.red),
                  onPressed: () => _removeDocument(e.key),
                ),
              )),
          OutlinedButton.icon(
            onPressed: _pickDocument,
            icon: const Icon(Icons.attach_file),
            label: const Text('Attach Document (PDF / Image, max 10 MB)'),
          ),
        ],
      );

  // ── Submit button ────────────────────────────────────────────────────────

  Widget _buildSubmitButton(AsyncValue<dynamic> state) {
    final isLoading = state is AsyncLoading;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (state is AsyncError)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                border: Border.all(color: Colors.red.shade200),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                'Error: ${state.error}',
                style: TextStyle(color: Colors.red.shade800, fontSize: 13),
              ),
            ),
          ),
        SizedBox(
          height: 48,
          child: ElevatedButton(
            onPressed: isLoading ? null : _submit,
            style: ElevatedButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.primary,
              foregroundColor: Colors.white,
            ),
            child: isLoading
                ? const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white),
                  )
                : const Text('Register Patient', style: TextStyle(fontSize: 16)),
          ),
        ),
      ],
    );
  }
}

// ── Helper widgets ─────────────────────────────────────────────────────────

class _SectionHeader extends StatelessWidget {
  final String title;
  const _SectionHeader(this.title);

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(
          title,
          style: Theme.of(context)
              .textTheme
              .titleSmall
              ?.copyWith(fontWeight: FontWeight.bold),
        ),
      );
}

class _OfflineBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        color: Colors.orange.shade700,
        padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 16),
        child: const Row(
          children: [
            Icon(Icons.wifi_off, color: Colors.white, size: 16),
            SizedBox(width: 8),
            Text(
              'You are offline — registration will be queued and synced automatically.',
              style: TextStyle(color: Colors.white, fontSize: 12),
            ),
          ],
        ),
      );
}
