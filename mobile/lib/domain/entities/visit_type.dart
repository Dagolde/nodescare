class VisitType {
  final String id;
  final String name;
  final String slug;
  final bool isDefault;
  final bool isActive;
  final int sortOrder;

  const VisitType({
    required this.id,
    required this.name,
    required this.slug,
    required this.isDefault,
    required this.isActive,
    required this.sortOrder,
  });

  factory VisitType.fromJson(Map<String, dynamic> json) => VisitType(
        id: json['id'] as String,
        name: json['name'] as String,
        slug: json['slug'] as String,
        isDefault: json['is_default'] as bool? ?? false,
        isActive: json['is_active'] as bool? ?? true,
        sortOrder: json['sort_order'] as int? ?? 0,
      );
}
