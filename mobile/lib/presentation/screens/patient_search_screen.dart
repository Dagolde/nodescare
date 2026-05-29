import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import '../providers/accounts_provider.dart';
import '../providers/department_provider.dart';
import '../providers/patient_provider.dart';

/// Search result model
class PatientSearchResult {
  final String id;
  final String patientNumber;
  final String fullName;
  final String dateOfBirth;
  final String gender;
  final String phone;
  final int visitCount;
  final String? lastVisit;
  final String? photoObjectKey;

  PatientSearchResult({
    required this.id,
    required this.patientNumber,
    required this.fullName,
    required this.dateOfBirth,
    required this.gender,
    required this.phone,
    required this.visitCount,
    this.lastVisit,
    this.photoObjectKey,
  });

  factory PatientSearchResult.fromJson(Map<String, dynamic> json) {
    return PatientSearchResult(
      id: json['id'] as String,
      patientNumber: json['patient_number'] as String,
      fullName: json['full_name'] as String,
      dateOfBirth: json['date_of_birth'] as String,
      gender: json['gender'] as String,
      phone: json['phone'] as String,
      visitCount: json['visit_count'] as int? ?? 0,
      lastVisit: json['last_visit'] as String?,
      photoObjectKey: json['photo_object_key'] as String?,
    );
  }
}

/// Patient search screen for front desk — find returning patients
class PatientSearchScreen extends ConsumerStatefulWidget {
  const PatientSearchScreen({super.key});

  @override
  ConsumerState<PatientSearchScreen> createState() =>
      _PatientSearchScreenState();
}

class _PatientSearchScreenState extends ConsumerState<PatientSearchScreen> {
  final _searchController = TextEditingController();
  List<PatientSearchResult> _results = [];
  bool _isSearching = false;
  String? _error;
  bool _hasSearched = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _search(String query) async {
    if (query.trim().length < 2) return;

    setState(() {
      _isSearching = true;
      _error = null;
      _hasSearched = true;
    });

    try {
      final remote = ref.read(remoteDataSourceProvider);
      final response = await remote.get(
        '/api/v1/patients/search',
        queryParameters: {'q': query.trim()},
      );

      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        setState(() {
          _results = data
              .map((j) => PatientSearchResult.fromJson(j))
              .toList();
        });
      } else {
        setState(() {
          _error = response.data['error']?['message'] ?? 'Search failed.';
        });
      }
    } on DioException catch (e) {
      final serverMsg = e.response?.data?['error']?['message']
          ?? e.response?.data?['message'];
      setState(() {
        _error = serverMsg != null
            ? 'Search error: $serverMsg'
            : 'Search failed (${e.response?.statusCode ?? 'no response'}). Check your connection.';
      });
    } catch (e) {
      setState(() {
        _error = 'Search failed: $e';
      });
    } finally {
      setState(() => _isSearching = false);
    }
  }

  Future<void> _createNewVisit(PatientSearchResult patient) async {
    // Step 1: Ask for chief complaint and workflow template
    final result = await _askVisitDetails(patient.fullName);
    if (result == null) return;

    setState(() => _isSearching = true);

    try {
      final remote = ref.read(remoteDataSourceProvider);
      final body = <String, dynamic>{'chief_complaint': result['complaint']};
      if (result['workflow_template_id'] != null) {
        body['workflow_template_id'] = result['workflow_template_id'];
      }
      final response = await remote.post(
        '/api/v1/patients/${patient.id}/new-visit',
        data: body,
      );

      if (response.data['success'] == true) {
        if (mounted) {
          ref.read(departmentQueueNotifierProvider.notifier).refresh();
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${patient.fullName} checked in. Routed to Accounts.'),
              backgroundColor: Colors.green,
            ),
          );
          Navigator.pop(context, true);
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to create visit: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _isSearching = false);
    }
  }

  Future<Map<String, dynamic>?> _askVisitDetails(String patientName) async {
    final complaintController = TextEditingController();
    String? selectedTemplateId;
    List<Map<String, dynamic>> templates = [];

    // Fetch workflow templates
    try {
      final remote = ref.read(remoteDataSourceProvider);
      final resp = await remote.get('/api/v1/workflows/templates');
      if (resp.data['success'] == true) {
        templates = List<Map<String, dynamic>>.from(
          (resp.data['data'] as List).map((t) => {'id': t['id'], 'name': t['name']}),
        );
      }
    } catch (_) {
      // If fetch fails, proceed without template selection
    }

    return showDialog<Map<String, dynamic>>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDialogState) => AlertDialog(
          title: Text('Check In — $patientName'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Chief Complaint', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                const SizedBox(height: 6),
                TextField(
                  controller: complaintController,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    hintText: 'e.g. Fever and headache, Lab test only',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                ),
                if (templates.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  const Text('Visit Type', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  const SizedBox(height: 6),
                  DropdownButtonFormField<String>(
                    value: selectedTemplateId,
                    decoration: const InputDecoration(
                      border: OutlineInputBorder(),
                      isDense: true,
                      hintText: 'Standard OPD (default)',
                    ),
                    items: [
                      const DropdownMenuItem<String>(
                        value: null,
                        child: Text('Standard OPD (default)'),
                      ),
                      ...templates.map((t) => DropdownMenuItem<String>(
                        value: t['id'] as String,
                        child: Text(t['name'] as String),
                      )),
                    ],
                    onChanged: (val) => setDialogState(() {
                      selectedTemplateId = val;
                    }),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(
              onPressed: () {
                final complaint = complaintController.text.trim();
                if (complaint.isEmpty) return;
                Navigator.pop(ctx, {
                  'complaint': complaint,
                  'workflow_template_id': selectedTemplateId,
                });
              },
              child: const Text('Check In'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Find Returning Patient'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
      ),
      body: Column(
        children: [
          // Search bar
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'Search by name, patient number, or phone...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          setState(() {
                            _results = [];
                            _hasSearched = false;
                          });
                        },
                      )
                    : null,
                border: const OutlineInputBorder(),
                filled: true,
                fillColor: Colors.grey[50],
              ),
              onChanged: (value) {
                setState(() {});
                if (value.trim().length >= 2) {
                  _search(value);
                }
              },
              onSubmitted: _search,
            ),
          ),

          // Results
          Expanded(
            child: _isSearching
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Text(_error!,
                            style: const TextStyle(color: Colors.red)))
                    : !_hasSearched
                        ? const Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.person_search,
                                    size: 64, color: Colors.grey),
                                SizedBox(height: 16),
                                Text(
                                  'Type at least 2 characters to search',
                                  style: TextStyle(
                                      color: Colors.grey, fontSize: 16),
                                ),
                              ],
                            ),
                          )
                        : _results.isEmpty
                            ? Center(
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    const Icon(Icons.person_off,
                                        size: 64, color: Colors.grey),
                                    const SizedBox(height: 16),
                                    const Text(
                                      'No patient found',
                                      style: TextStyle(
                                          fontSize: 18, color: Colors.grey),
                                    ),
                                    const SizedBox(height: 8),
                                    const Text(
                                      'This may be a new patient',
                                      style: TextStyle(color: Colors.grey),
                                    ),
                                  ],
                                ),
                              )
                            : ListView.builder(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 16),
                                itemCount: _results.length,
                                itemBuilder: (context, i) =>
                                    _PatientResultCard(
                                  patient: _results[i],
                                  onCheckIn: () =>
                                      _createNewVisit(_results[i]),
                                ),
                              ),
          ),
        ],
      ),
    );
  }
}

class _PatientResultCard extends ConsumerWidget {
  final PatientSearchResult patient;
  final VoidCallback onCheckIn;

  const _PatientResultCard({
    required this.patient,
    required this.onCheckIn,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                // Show photo if available, otherwise initials
                patient.photoObjectKey != null
                    ? _SearchResultAvatar(
                        patientId: patient.id,
                        name: patient.fullName,
                      )
                    : CircleAvatar(
                        backgroundColor: Colors.teal,
                        child: Text(
                          patient.fullName[0].toUpperCase(),
                          style: const TextStyle(
                              color: Colors.white, fontWeight: FontWeight.bold),
                        ),
                      ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        patient.fullName,
                        style: const TextStyle(
                            fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                      Text(
                        'No: ${patient.patientNumber}',
                        style: TextStyle(
                            color: Colors.grey[600], fontSize: 13),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${patient.visitCount} visit${patient.visitCount != 1 ? 's' : ''}',
                    style: TextStyle(
                        color: Colors.blue.shade700,
                        fontSize: 12,
                        fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Icon(Icons.phone, size: 14, color: Colors.grey[500]),
                const SizedBox(width: 4),
                Text(patient.phone,
                    style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                const SizedBox(width: 16),
                Icon(Icons.cake, size: 14, color: Colors.grey[500]),
                const SizedBox(width: 4),
                Text(patient.dateOfBirth,
                    style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                if (patient.lastVisit != null) ...[
                  const SizedBox(width: 16),
                  Icon(Icons.history, size: 14, color: Colors.grey[500]),
                  const SizedBox(width: 4),
                  Text('Last: ${patient.lastVisit}',
                      style:
                          TextStyle(color: Colors.grey[600], fontSize: 13)),
                ],
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: onCheckIn,
                icon: const Icon(Icons.login),
                label: const Text('Check In (New Visit)'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.teal,
                  foregroundColor: Colors.white,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Avatar widget that fetches the patient photo from the server
class _SearchResultAvatar extends ConsumerWidget {
  final String patientId;
  final String name;

  const _SearchResultAvatar({required this.patientId, required this.name});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final photoAsync = ref.watch(patientPhotoBytesProvider(patientId));
    return photoAsync.when(
      data: (bytes) => bytes != null
          ? CircleAvatar(
              backgroundImage: MemoryImage(bytes),
              radius: 20,
            )
          : _initialsAvatar(name),
      loading: () => _initialsAvatar(name),
      error: (_, __) => _initialsAvatar(name),
    );
  }

  Widget _initialsAvatar(String name) => CircleAvatar(
        backgroundColor: Colors.teal,
        radius: 20,
        child: Text(
          name[0].toUpperCase(),
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      );
}
