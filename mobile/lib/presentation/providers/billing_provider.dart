import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'accounts_provider.dart';

// ─── Pharmacy Billing ─────────────────────────────────────────────────────────

class PharmacyBillingNotifier extends StateNotifier<AsyncValue<void>> {
  final ref;

  PharmacyBillingNotifier(this.ref) : super(const AsyncValue.data(null));

  Future<void> submitPharmacyBilling(
    String patientRecordId,
    List<Map<String, dynamic>> items,
  ) async {
    state = const AsyncValue.loading();
    try {
      final remote = ref.read(remoteDataSourceProvider);
      final response = await remote.post(
        '/api/v1/patient-records/$patientRecordId/pharmacy-billing',
        data: {'items': items},
      );
      if (response.data['success'] == true) {
        state = const AsyncValue.data(null);
      } else {
        throw Exception(response.data['error']?['message'] ?? 'Pharmacy billing failed');
      }
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final pharmacyBillingProvider =
    StateNotifierProvider<PharmacyBillingNotifier, AsyncValue<void>>((ref) {
  return PharmacyBillingNotifier(ref);
});

// ─── Lab Billing ──────────────────────────────────────────────────────────────

class LabBillingNotifier extends StateNotifier<AsyncValue<void>> {
  final ref;

  LabBillingNotifier(this.ref) : super(const AsyncValue.data(null));

  Future<void> submitLabBilling(
    String patientRecordId,
    List<Map<String, dynamic>> items,
  ) async {
    state = const AsyncValue.loading();
    try {
      final remote = ref.read(remoteDataSourceProvider);
      final response = await remote.post(
        '/api/v1/patient-records/$patientRecordId/lab-billing',
        data: {'items': items},
      );
      if (response.data['success'] == true) {
        state = const AsyncValue.data(null);
      } else {
        throw Exception(response.data['error']?['message'] ?? 'Lab billing failed');
      }
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final labBillingProvider =
    StateNotifierProvider<LabBillingNotifier, AsyncValue<void>>((ref) {
  return LabBillingNotifier(ref);
});

// ─── Admission Billing ────────────────────────────────────────────────────────

class AdmissionBillingNotifier extends StateNotifier<AsyncValue<void>> {
  final ref;

  AdmissionBillingNotifier(this.ref) : super(const AsyncValue.data(null));

  Future<void> submitAdmission(
    String patientRecordId,
    String wardDepartmentId,
  ) async {
    state = const AsyncValue.loading();
    try {
      final remote = ref.read(remoteDataSourceProvider);
      final response = await remote.post(
        '/api/v1/patient-records/$patientRecordId/admit',
        data: {'ward_department_id': wardDepartmentId},
      );
      if (response.data['success'] == true) {
        state = const AsyncValue.data(null);
      } else {
        throw Exception(response.data['error']?['message'] ?? 'Admission billing failed');
      }
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final admissionBillingProvider =
    StateNotifierProvider<AdmissionBillingNotifier, AsyncValue<void>>((ref) {
  return AdmissionBillingNotifier(ref);
});

// ─── Confirm Dispense ─────────────────────────────────────────────────────────

class ConfirmDispenseNotifier extends StateNotifier<AsyncValue<void>> {
  final ref;

  ConfirmDispenseNotifier(this.ref) : super(const AsyncValue.data(null));

  Future<void> confirmDispense(
    String patientRecordId, {
    String? notes,
  }) async {
    state = const AsyncValue.loading();
    try {
      final remote = ref.read(remoteDataSourceProvider);
      final response = await remote.post(
        '/api/v1/patient-records/$patientRecordId/confirm-dispense',
        data: notes != null ? {'notes': notes} : {},
      );
      if (response.data['success'] == true) {
        state = const AsyncValue.data(null);
      } else {
        throw Exception(response.data['error']?['message'] ?? 'Confirm dispense failed');
      }
    } catch (e, stack) {
      state = AsyncValue.error(e, stack);
    }
  }

  void reset() => state = const AsyncValue.data(null);
}

final confirmDispenseProvider =
    StateNotifierProvider<ConfirmDispenseNotifier, AsyncValue<void>>((ref) {
  return ConfirmDispenseNotifier(ref);
});
