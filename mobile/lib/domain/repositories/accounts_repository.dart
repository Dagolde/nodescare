import '../entities/invoice.dart';
import '../entities/patient_record.dart';

abstract class AccountsRepository {
  Future<List<PatientRecord>> getAccountsQueue();
  Future<Invoice> createInvoice(String patientRecordId, List<InvoiceItem> items);
  Future<List<Invoice>> getInvoices(String patientRecordId);
  Future<Invoice> recordPayment(String invoiceId, Map<String, dynamic> paymentData);
  Future<Invoice> settleInvoice(String invoiceId);
  Future<String> generateReceipt(String invoiceId);
}
