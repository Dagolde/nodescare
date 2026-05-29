class Invoice {
  final String id;
  final String patientRecordId;
  final String invoiceNumber;
  final double totalAmount;
  final double paidAmount;
  final String status;
  final String createdBy;
  final DateTime createdAt;
  final List<InvoiceItem> items;
  final List<InvoicePayment> payments;

  Invoice({
    required this.id,
    required this.patientRecordId,
    required this.invoiceNumber,
    required this.totalAmount,
    required this.paidAmount,
    required this.status,
    required this.createdBy,
    required this.createdAt,
    this.items = const [],
    this.payments = const [],
  });

  double get outstandingBalance => totalAmount - paidAmount;
  bool get isPaid => status == 'paid';

  factory Invoice.fromJson(Map<String, dynamic> json) {
    return Invoice(
      id: json['id'] as String,
      patientRecordId: json['patient_record_id'] as String,
      invoiceNumber: json['invoice_number'] as String,
      totalAmount: double.parse((json['total_amount'] ?? 0).toString()),
      paidAmount: double.parse((json['paid_amount'] ?? 0).toString()),
      status: json['status'] as String,
      createdBy: json['created_by'] as String? ?? '',
      createdAt: DateTime.parse(json['created_at'] as String),
      items: json['items'] != null
          ? (json['items'] as List).map((i) => InvoiceItem.fromJson(i)).toList()
          : [],
      payments: json['payments'] != null
          ? (json['payments'] as List).map((p) => InvoicePayment.fromJson(p)).toList()
          : [],
    );
  }
}

class InvoiceItem {
  final String id;
  final String invoiceId;
  final String description;
  final double unitPrice;
  final int quantity;
  final double totalPrice;

  InvoiceItem({
    required this.id,
    required this.invoiceId,
    required this.description,
    required this.unitPrice,
    required this.quantity,
    required this.totalPrice,
  });

  factory InvoiceItem.fromJson(Map<String, dynamic> json) {
    return InvoiceItem(
      id: json['id'] as String,
      invoiceId: json['invoice_id'] as String,
      description: json['description'] as String,
      unitPrice: double.parse((json['unit_price'] ?? 0).toString()),
      quantity: json['quantity'] as int,
      totalPrice: double.parse((json['total_price'] ?? 0).toString()),
    );
  }

  Map<String, dynamic> toJson() => {
        'description': description,
        'unit_price': unitPrice,
        'quantity': quantity,
      };
}

class InvoicePayment {
  final String id;
  final String invoiceId;
  final String accountantId;
  final double amount;
  final String paymentMethod;
  final String? reference;
  final String? notes;
  final DateTime createdAt;

  InvoicePayment({
    required this.id,
    required this.invoiceId,
    required this.accountantId,
    required this.amount,
    required this.paymentMethod,
    this.reference,
    this.notes,
    required this.createdAt,
  });

  factory InvoicePayment.fromJson(Map<String, dynamic> json) {
    return InvoicePayment(
      id: json['id'] as String,
      invoiceId: json['invoice_id'] as String,
      accountantId: json['accountant_id'] as String? ?? '',
      amount: double.parse((json['amount'] ?? 0).toString()),
      paymentMethod: json['payment_method'] as String? ?? 'cash',
      reference: json['reference'] as String?,
      notes: json['notes'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
