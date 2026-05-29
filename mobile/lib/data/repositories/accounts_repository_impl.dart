import 'package:dio/dio.dart';
import '../../domain/entities/invoice.dart';
import '../../domain/entities/patient_record.dart';
import '../../domain/repositories/accounts_repository.dart';
import '../data_sources/remote_data_source.dart';

class AccountsRepositoryImpl implements AccountsRepository {
  final RemoteDataSource remoteDataSource;

  AccountsRepositoryImpl({required this.remoteDataSource});

  @override
  Future<List<PatientRecord>> getAccountsQueue() async {
    try {
      final response = await remoteDataSource.get('/api/v1/accounts/queue');
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => PatientRecord.fromJson(json)).toList();
      }
      throw Exception('Failed to fetch accounts queue');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to fetch accounts queue';
      throw Exception(msg);
    }
  }

  @override
  Future<Invoice> createInvoice(
    String patientRecordId,
    List<InvoiceItem> items,
  ) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/patient-records/$patientRecordId/invoices',
        data: {
          'items': items.map((item) => item.toJson()).toList(),
        },
      );
      if (response.data['success'] == true) {
        return Invoice.fromJson(response.data['data']);
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to create invoice');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to create invoice';
      throw Exception(msg);
    }
  }

  @override
  Future<List<Invoice>> getInvoices(String patientRecordId) async {
    try {
      final response = await remoteDataSource.get(
        '/api/v1/patient-records/$patientRecordId/invoices',
      );
      if (response.data['success'] == true) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => Invoice.fromJson(json)).toList();
      }
      throw Exception('Failed to fetch invoices');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to fetch invoices';
      throw Exception(msg);
    }
  }

  @override
  Future<Invoice> recordPayment(
    String invoiceId,
    Map<String, dynamic> paymentData,
  ) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/invoices/$invoiceId/payments',
        data: paymentData,
      );
      if (response.data['success'] == true) {
        return Invoice.fromJson(response.data['data']['invoice']);
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to record payment');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to record payment';
      throw Exception(msg);
    }
  }

  @override
  Future<Invoice> settleInvoice(String invoiceId) async {
    try {
      final response = await remoteDataSource.post(
        '/api/v1/invoices/$invoiceId/settle',
      );
      if (response.data['success'] == true) {
        return Invoice.fromJson(response.data['data']['invoice']);
      }
      throw Exception(response.data['error']?['message'] ?? 'Failed to settle invoice');
    } on DioException catch (e) {
      final msg = e.response?.data?['error']?['message'] ?? 'Failed to settle invoice';
      throw Exception(msg);
    }
  }

  @override
  Future<String> generateReceipt(String invoiceId) async {
    return '/api/v1/invoices/$invoiceId/receipt';
  }
}
