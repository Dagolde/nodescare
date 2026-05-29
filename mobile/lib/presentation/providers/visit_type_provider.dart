import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../domain/entities/visit_type.dart';
import '../../domain/entities/doctor_summary.dart';
import 'accounts_provider.dart';

/// Fetches all active visit types for the current tenant.
final visitTypesProvider = FutureProvider<List<VisitType>>((ref) async {
  final ds = ref.watch(remoteDataSourceProvider);
  final response = await ds.get('/api/v1/visit-types');
  final data = response.data['data'] as List<dynamic>;
  return data.map((json) => VisitType.fromJson(json as Map<String, dynamic>)).toList();
});

/// Fetches doctors filtered by visit type slug.
final doctorsByVisitTypeProvider =
    FutureProvider.family<List<DoctorSummary>, String>((ref, slug) async {
  final ds = ref.watch(remoteDataSourceProvider);
  final response = await ds.get('/api/v1/doctors/by-visit-type/$slug');
  final data = response.data['data'] as List<dynamic>;
  return data.map((json) => DoctorSummary.fromJson(json as Map<String, dynamic>)).toList();
});
