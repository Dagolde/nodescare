import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/accounts_provider.dart';
import 'invoice_creation_screen.dart';

class AccountsQueueScreen extends ConsumerWidget {
  const AccountsQueueScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final queueAsync = ref.watch(accountsQueueProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Accounts Queue'),
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.refresh(accountsQueueProvider),
          ),
        ],
      ),
      body: queueAsync.when(
        data: (records) {
          if (records.isEmpty) {
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.inbox, size: 64, color: Colors.grey),
                  SizedBox(height: 16),
                  Text(
                    'No pending payments',
                    style: TextStyle(fontSize: 18, color: Colors.grey),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async {
              return ref.refresh(accountsQueueProvider.future);
            },
            child: ListView.builder(
              itemCount: records.length,
              padding: const EdgeInsets.all(16),
              itemBuilder: (context, index) {
                final record = records[index];
                final patient = record.patient;

                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(16),
                    leading: CircleAvatar(
                      backgroundColor: Theme.of(context).colorScheme.primary,
                      child: Text(
                        patient?.fullName.substring(0, 1).toUpperCase() ?? 'P',
                        style: const TextStyle(color: Colors.white),
                      ),
                    ),
                    title: Text(
                      patient?.fullName ?? 'Unknown Patient',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 4),
                        Text('Patient No: ${patient?.patientNumber ?? 'N/A'}'),
                        Text('Registered: ${_formatDateTime(record.createdAt)}'),
                        Text('Chief Complaint: ${patient?.chiefComplaint ?? 'N/A'}'),
                        if (record.paymentReason != null) ...[
                          const SizedBox(height: 6),
                          _PaymentReasonBadge(
                            paymentReason: record.paymentReason!,
                            paymentContext: record.paymentContext,
                          ),
                        ],
                      ],
                    ),
                    trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => InvoiceCreationScreen(
                            patientRecord: record,
                          ),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 64, color: Colors.red),
              const SizedBox(height: 16),
              Text(
                'Error: ${error.toString()}',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.refresh(accountsQueueProvider),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatDateTime(DateTime dateTime) {
    return '${dateTime.day}/${dateTime.month}/${dateTime.year} ${dateTime.hour}:${dateTime.minute.toString().padLeft(2, '0')}';
  }
}

class _PaymentReasonBadge extends StatelessWidget {
  final String paymentReason;
  final String? paymentContext;

  const _PaymentReasonBadge({
    required this.paymentReason,
    this.paymentContext,
  });

  Color get _color {
    switch (paymentContext) {
      case 'pharmacy_billing':
        return Colors.purple;
      case 'lab_billing':
        return Colors.blue;
      case 'admission_billing':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: _color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _color, width: 1),
      ),
      child: Text(
        paymentReason,
        style: TextStyle(
          color: _color,
          fontSize: 11,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }
}
