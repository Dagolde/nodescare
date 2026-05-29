# Flutter wrapper
-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

# SLF4J - suppress missing StaticLoggerBinder (used by pusher_channels_flutter)
-dontwarn org.slf4j.**
-dontwarn org.slf4j.impl.**

# Google Play Core (Flutter deferred components - not used but referenced)
-dontwarn com.google.android.play.core.**
-keep class com.google.android.play.core.** { *; }

# Pusher
-keep class com.pusher.** { *; }
-dontwarn com.pusher.**

# OkHttp (used by Pusher)
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn javax.annotation.**

# Firebase
-keep class com.google.firebase.** { *; }
-dontwarn com.google.firebase.**

# General
-dontwarn java.lang.invoke.**
-dontwarn **$$Lambda$*
-dontwarn kotlin.**
