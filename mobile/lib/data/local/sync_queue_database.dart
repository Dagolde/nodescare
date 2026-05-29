import 'dart:convert';
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

/// SQLite-backed local database for offline sync queue.
/// Tokens and sensitive data are stored separately in flutter_secure_storage.
class SyncQueueDatabase {
  static const _dbName = 'hhms_sync_queue.db';
  static const _dbVersion = 1;
  static const table = 'sync_queue';

  static Database? _db;

  SyncQueueDatabase();

  Future<Database> get database async {
    _db ??= await _open();
    return _db!;
  }

  Future<Database> _open() async {
    final path = join(await getDatabasesPath(), _dbName);
    return openDatabase(
      path,
      version: _dbVersion,
      onCreate: _onCreate,
    );
  }

  Future<void> _onCreate(Database db, int version) async {
    await db.execute('''
      CREATE TABLE $table (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        operation_type  TEXT    NOT NULL,
        payload         TEXT    NOT NULL,
        status          TEXT    NOT NULL DEFAULT 'pending',
        created_at      TEXT    NOT NULL,
        synced_at       TEXT,
        retry_count     INTEGER NOT NULL DEFAULT 0
      )
    ''');
  }

  /// Insert a new pending operation into the queue.
  Future<int> enqueue({
    required String operationType,
    required Map<String, dynamic> payload,
  }) async {
    final db = await database;
    return db.insert(table, {
      'operation_type': operationType,
      'payload': jsonEncode(payload),
      'status': 'pending',
      'created_at': DateTime.now().toIso8601String(),
      'retry_count': 0,
    });
  }

  /// Fetch all rows with status = 'pending', ordered FIFO.
  Future<List<Map<String, dynamic>>> getPending() async {
    final db = await database;
    return db.query(
      table,
      where: 'status = ?',
      whereArgs: ['pending'],
      orderBy: 'id ASC',
    );
  }

  /// Mark a queued item as synced.
  Future<void> markSynced(int id) async {
    final db = await database;
    await db.update(
      table,
      {
        'status': 'synced',
        'synced_at': DateTime.now().toIso8601String(),
      },
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  /// Increment retry count; mark as 'conflict' after 3 retries.
  Future<void> incrementRetry(int id) async {
    final db = await database;
    final rows = await db.query(table, where: 'id = ?', whereArgs: [id]);
    if (rows.isEmpty) return;
    final retryCount = (rows.first['retry_count'] as int? ?? 0) + 1;
    await db.update(
      table,
      {
        'retry_count': retryCount,
        'status': retryCount >= 3 ? 'conflict' : 'pending',
      },
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  Future<void> close() async {
    await _db?.close();
    _db = null;
  }
}
