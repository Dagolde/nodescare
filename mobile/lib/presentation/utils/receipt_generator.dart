import 'dart:io';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:path_provider/path_provider.dart';
import '../../domain/entities/invoice.dart';
import '../../domain/entities/patient_record.dart';

class ReceiptGenerator {
  static Future<void> generateAndPrintReceipt(
    Invoice invoice,
    PatientRecord patientRecord,
  ) async {
    final pdf = _buildPdf(invoice, patientRecord);
    await Printing.layoutPdf(
      onLayout: (PdfPageFormat format) async => pdf.save(),
    );
  }

  static Future<File> saveReceiptToFile(
    Invoice invoice,
    PatientRecord patientRecord,
  ) async {
    final pdf = _buildPdf(invoice, patientRecord);
    final output = await getTemporaryDirectory();
    final file = File('${output.path}/receipt_${invoice.invoiceNumber}.pdf');
    await file.writeAsBytes(await pdf.save());
    return file;
  }

  static pw.Document _buildPdf(Invoice invoice, PatientRecord patientRecord) {
    final pdf = pw.Document();

    pdf.addPage(
      pw.Page(
        pageFormat: PdfPageFormat.a4,
        build: (pw.Context context) {
          return pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              // Header
              pw.Center(
                child: pw.Column(
                  children: [
                    pw.Text(
                      'HOSPITAL HEALTH MANAGEMENT SYSTEM',
                      style: pw.TextStyle(
                        fontSize: 18,
                        fontWeight: pw.FontWeight.bold,
                      ),
                    ),
                    pw.SizedBox(height: 6),
                    pw.Text(
                      'PAYMENT RECEIPT',
                      style: pw.TextStyle(
                        fontSize: 14,
                        fontWeight: pw.FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
              pw.SizedBox(height: 20),

              // Invoice details
              pw.Row(
                mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                children: [
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.start,
                    children: [
                      pw.Text('Invoice: ${invoice.invoiceNumber}'),
                      pw.Text('Date: ${_formatDate(invoice.createdAt)}'),
                    ],
                  ),
                  pw.Column(
                    crossAxisAlignment: pw.CrossAxisAlignment.end,
                    children: [
                      pw.Text('Status: ${invoice.status.toUpperCase()}'),
                    ],
                  ),
                ],
              ),
              pw.SizedBox(height: 16),

              // Patient details
              pw.Container(
                padding: const pw.EdgeInsets.all(10),
                decoration: pw.BoxDecoration(
                  border: pw.Border.all(color: PdfColors.grey),
                  borderRadius: const pw.BorderRadius.all(pw.Radius.circular(4)),
                ),
                child: pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.Text(
                      'Patient Information',
                      style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
                    ),
                    pw.SizedBox(height: 6),
                    pw.Text('Name: ${patientRecord.patient?.fullName ?? "N/A"}'),
                    pw.Text('Patient No: ${patientRecord.patient?.patientNumber ?? "N/A"}'),
                    pw.Text('Phone: ${patientRecord.patient?.phone ?? "N/A"}'),
                  ],
                ),
              ),
              pw.SizedBox(height: 20),

              // Invoice items table
              pw.Text(
                'Invoice Items',
                style: pw.TextStyle(fontSize: 13, fontWeight: pw.FontWeight.bold),
              ),
              pw.SizedBox(height: 6),
              pw.Table(
                border: pw.TableBorder.all(color: PdfColors.grey),
                children: [
                  pw.TableRow(
                    decoration: const pw.BoxDecoration(color: PdfColors.grey300),
                    children: [
                      _cell('Description', isHeader: true),
                      _cell('Unit Price', isHeader: true),
                      _cell('Qty', isHeader: true),
                      _cell('Total', isHeader: true),
                    ],
                  ),
                  ...invoice.items.map((item) => pw.TableRow(
                        children: [
                          _cell(item.description),
                          _cell('₦${item.unitPrice.toStringAsFixed(2)}'),
                          _cell(item.quantity.toString()),
                          _cell('₦${item.totalPrice.toStringAsFixed(2)}'),
                        ],
                      )),
                ],
              ),
              pw.SizedBox(height: 14),

              // Totals
              pw.Align(
                alignment: pw.Alignment.centerRight,
                child: pw.SizedBox(
                  width: 240,
                  child: pw.Column(
                    children: [
                      _totalRow('Total Amount:', '₦${invoice.totalAmount.toStringAsFixed(2)}', isBold: true),
                      _totalRow('Amount Paid:', '₦${invoice.paidAmount.toStringAsFixed(2)}'),
                      pw.Divider(),
                      _totalRow('Outstanding:', '₦${invoice.outstandingBalance.toStringAsFixed(2)}'),
                    ],
                  ),
                ),
              ),
              pw.SizedBox(height: 20),

              // Payment history
              if (invoice.payments.isNotEmpty) ...[
                pw.Text(
                  'Payment History',
                  style: pw.TextStyle(fontSize: 13, fontWeight: pw.FontWeight.bold),
                ),
                pw.SizedBox(height: 6),
                pw.Table(
                  border: pw.TableBorder.all(color: PdfColors.grey),
                  children: [
                    pw.TableRow(
                      decoration: const pw.BoxDecoration(color: PdfColors.grey300),
                      children: [
                        _cell('Date', isHeader: true),
                        _cell('Method', isHeader: true),
                        _cell('Amount', isHeader: true),
                        _cell('Reference', isHeader: true),
                      ],
                    ),
                    ...invoice.payments.map((p) => pw.TableRow(
                          children: [
                            _cell(_formatDate(p.createdAt)),
                            _cell(p.paymentMethod.toUpperCase()),
                            _cell('₦${p.amount.toStringAsFixed(2)}'),
                            _cell(p.reference ?? '-'),
                          ],
                        )),
                  ],
                ),
              ],

              pw.Spacer(),
              pw.Divider(),
              pw.Center(
                child: pw.Text(
                  'Thank you for your payment',
                  style: const pw.TextStyle(fontSize: 10),
                ),
              ),
            ],
          );
        },
      ),
    );

    return pdf;
  }

  static pw.Widget _cell(String text, {bool isHeader = false}) {
    return pw.Padding(
      padding: const pw.EdgeInsets.all(6),
      child: pw.Text(
        text,
        style: pw.TextStyle(
          fontWeight: isHeader ? pw.FontWeight.bold : pw.FontWeight.normal,
          fontSize: isHeader ? 10 : 9,
        ),
      ),
    );
  }

  static pw.Widget _totalRow(String label, String value, {bool isBold = false}) {
    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 3),
      child: pw.Row(
        mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
        children: [
          pw.Text(label, style: pw.TextStyle(fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal)),
          pw.Text(value, style: pw.TextStyle(fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal)),
        ],
      ),
    );
  }

  static String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year} ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
  }
}
