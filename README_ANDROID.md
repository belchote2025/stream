# 🚀 Guía de Inicio Rápido - Apps Android

Esta guía te ayudará a comenzar con el desarrollo de las aplicaciones Android y Android TV para tu plataforma de streaming.

## 📋 Requisitos Previos

### Software Necesario
- ✅ **Android Studio** Hedgehog (2023.1.1) o superior
- ✅ **JDK** 17 o superior
- ✅ **Kotlin** 1.9.0 o superior
- ✅ **Git** para control de versiones

### Conocimientos Recomendados
- Kotlin básico/intermedio
- Jetpack Compose
- Arquitectura MVVM
- Coroutines y Flow
- REST APIs

## 🏗️ Estructura del Proyecto

```
streaming-platform/
├── android-app/              # Aplicación móvil Android
│   ├── build.gradle.kts
│   ├── README.md
│   └── app/
│       └── src/main/
│           ├── java/
│           └── res/
├── android-tv-app/           # Aplicación Android TV
│   ├── build.gradle.kts
│   ├── README.md
│   └── app/
│       └── src/main/
│           ├── java/
│           └── res/
└── README_ANDROID.md         # Este archivo
```

## 🎯 Paso 1: Configuración Inicial

### 1.1 Instalar Android Studio
1. Descarga desde [developer.android.com](https://developer.android.com/studio)
2. Instala con los componentes por defecto
3. Configura el SDK de Android (API 24-34)

### 1.2 Configurar el Proyecto

#### Para Android Mobile:
```bash
cd streaming-platform/android-app
```

#### Para Android TV:
```bash
cd streaming-platform/android-tv-app
```

### 1.3 Abrir en Android Studio
1. Abre Android Studio
2. File → Open
3. Selecciona la carpeta `android-app` o `android-tv-app`
4. Espera a que Gradle sincronice

## 🔧 Paso 2: Configuración de la API

### 2.1 Actualizar BASE_URL

Edita `build.gradle.kts`:
```kotlin
buildConfigField("String", "BASE_URL", "\"http://tu-servidor.com/streaming-platform/\"")
```

Para desarrollo local:
```kotlin
buildConfigField("String", "BASE_URL", "\"http://10.0.2.2/streaming-platform/\"")
// 10.0.2.2 es localhost desde el emulador Android
```

### 2.2 Configurar Network Security (Desarrollo)

Crea `res/xml/network_security_config.xml`:
```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">10.0.2.2</domain>
        <domain includeSubdomains="true">localhost</domain>
    </domain-config>
</network-security-config>
```

Agrega en `AndroidManifest.xml`:
```xml
<application
    android:networkSecurityConfig="@xml/network_security_config"
    ...>
```

## 📱 Paso 3: Crear Estructura Base

### 3.1 Crear Paquetes

Dentro de `app/src/main/java/com/streaming/`:

```
streaming/
├── data/
│   ├── api/
│   │   ├── StreamingApi.kt
│   │   └── ApiService.kt
│   ├── model/
│   │   ├── Content.kt
│   │   ├── User.kt
│   │   └── Response.kt
│   └── repository/
│       └── ContentRepository.kt
├── domain/
│   ├── model/
│   └── usecase/
├── presentation/
│   ├── home/
│   │   ├── HomeScreen.kt
│   │   └── HomeViewModel.kt
│   ├── player/
│   ├── search/
│   └── components/
├── di/
│   ├── AppModule.kt
│   └── NetworkModule.kt
└── utils/
    └── Constants.kt
```

### 3.2 Crear API Interface

`data/api/StreamingApi.kt`:
```kotlin
package com.streaming.data.api

import com.streaming.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface StreamingApi {
    @GET("api/content/index.php")
    suspend fun getContent(
        @Query("type") type: String? = null,
        @Query("sort") sort: String? = null,
        @Query("limit") limit: Int = 20
    ): Response<ContentResponse>
    
    @GET("api/content/index.php")
    suspend fun getContentById(
        @Query("id") id: Int
    ): Response<ContentDetail>
    
    @POST("api/auth/login.php")
    suspend fun login(
        @Body credentials: LoginRequest
    ): Response<AuthResponse>
    
    @GET("api/search.php")
    suspend fun search(
        @Query("q") query: String
    ): Response<SearchResponse>
}
```

### 3.3 Crear Modelos de Datos

`data/model/Content.kt`:
```kotlin
package com.streaming.data.model

import com.google.gson.annotations.SerializedName

data class Content(
    @SerializedName("id") val id: Int,
    @SerializedName("title") val title: String,
    @SerializedName("description") val description: String?,
    @SerializedName("poster_url") val posterUrl: String?,
    @SerializedName("backdrop_url") val backdropUrl: String?,
    @SerializedName("video_url") val videoUrl: String?,
    @SerializedName("type") val type: String,
    @SerializedName("release_year") val releaseYear: Int?,
    @SerializedName("rating") val rating: Double?,
    @SerializedName("duration") val duration: Int?,
    @SerializedName("is_premium") val isPremium: Boolean = false
)

data class ContentResponse(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: List<Content>,
    @SerializedName("total") val total: Int
)

data class ContentDetail(
    @SerializedName("success") val success: Boolean,
    @SerializedName("data") val data: Content
)
```

### 3.4 Configurar Hilt (DI)

`di/AppModule.kt`:
```kotlin
package com.streaming.di

import android.content.Context
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {
    
    @Provides
    @Singleton
    fun provideContext(@ApplicationContext context: Context): Context {
        return context
    }
}
```

`di/NetworkModule.kt`:
```kotlin
package com.streaming.di

import com.streaming.BuildConfig
import com.streaming.data.api.StreamingApi
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {
    
    @Provides
    @Singleton
    fun provideOkHttpClient(): OkHttpClient {
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = if (BuildConfig.DEBUG) {
                HttpLoggingInterceptor.Level.BODY
            } else {
                HttpLoggingInterceptor.Level.NONE
            }
        }
        
        return OkHttpClient.Builder()
            .addInterceptor(loggingInterceptor)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
    }
    
    @Provides
    @Singleton
    fun provideRetrofit(okHttpClient: OkHttpClient): Retrofit {
        return Retrofit.Builder()
            .baseUrl(BuildConfig.BASE_URL)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
    }
    
    @Provides
    @Singleton
    fun provideStreamingApi(retrofit: Retrofit): StreamingApi {
        return retrofit.create(StreamingApi::class.java)
    }
}
```

### 3.5 Crear Application Class

`StreamingApp.kt`:
```kotlin
package com.streaming

import android.app.Application
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class StreamingApp : Application() {
    override fun onCreate() {
        super.onCreate()
        // Inicialización global
    }
}
```

Actualizar `AndroidManifest.xml`:
```xml
<application
    android:name=".StreamingApp"
    ...>
```

## 🎨 Paso 4: Crear UI con Compose

### 4.1 MainActivity

```kotlin
package com.streaming

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.ui.Modifier
import com.streaming.presentation.theme.StreamingTheme
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            StreamingTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    // Navigation will go here
                    HomeScreen()
                }
            }
        }
    }
}
```

### 4.2 Tema

`presentation/theme/Theme.kt`:
```kotlin
package com.streaming.presentation.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val NetflixRed = Color(0xFFE50914)
private val NetflixBlack = Color(0xFF141414)

private val DarkColorScheme = darkColorScheme(
    primary = NetflixRed,
    background = NetflixBlack,
    surface = Color(0xFF1F1F1F),
    onPrimary = Color.White,
    onBackground = Color.White,
    onSurface = Color.White
)

@Composable
fun StreamingTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    MaterialTheme(
        colorScheme = DarkColorScheme,
        typography = Typography,
        content = content
    )
}
```

## 🧪 Paso 5: Testing

### 5.1 Ejecutar en Emulador

1. Crear AVD en Android Studio:
   - Tools → Device Manager → Create Device
   - Selecciona Pixel 6 (para mobile) o TV (1080p) para TV
   - Descarga system image (API 33 o 34)
   - Finish

2. Ejecutar:
   - Click en el botón Run (▶️)
   - O usa `Shift + F10`

### 5.2 Ejecutar en Dispositivo Físico

1. Habilita "Opciones de desarrollador" en tu dispositivo
2. Activa "Depuración USB"
3. Conecta el dispositivo
4. Autoriza la conexión
5. Ejecuta la app

## 📦 Paso 6: Build APK

### Debug APK
```bash
./gradlew assembleDebug
```
APK en: `app/build/outputs/apk/debug/app-debug.apk`

### Release APK
```bash
./gradlew assembleRelease
```
APK en: `app/build/outputs/apk/release/app-release.apk`

## 🔍 Troubleshooting

### Problema: Gradle Sync Failed
**Solución**: 
- File → Invalidate Caches → Invalidate and Restart
- Verifica conexión a internet
- Actualiza Gradle: `./gradlew wrapper --gradle-version=8.2`

### Problema: Cannot resolve symbol 'BuildConfig'
**Solución**:
- Build → Clean Project
- Build → Rebuild Project

### Problema: API no responde
**Solución**:
- Verifica que el servidor esté corriendo
- Usa `10.0.2.2` en lugar de `localhost` en emulador
- Revisa `network_security_config.xml`

### Problema: Imágenes no cargan
**Solución**:
- Verifica URLs de imágenes
- Agrega permisos de internet en manifest
- Revisa logs con Logcat

## 📚 Recursos Adicionales

### Documentación Oficial
- [Android Developers](https://developer.android.com/)
- [Jetpack Compose](https://developer.android.com/jetpack/compose)
- [Kotlin](https://kotlinlang.org/docs/home.html)

### Tutoriales Recomendados
- [Compose Pathway](https://developer.android.com/courses/pathways/compose)
- [Android Basics with Compose](https://developer.android.com/courses/android-basics-compose/course)

### Comunidad
- [Stack Overflow - Android](https://stackoverflow.com/questions/tagged/android)
- [Reddit - r/androiddev](https://reddit.com/r/androiddev)
- [Kotlin Slack](https://kotlinlang.slack.com/)

## 🎯 Próximos Pasos

1. ✅ Configurar proyecto base
2. ⬜ Implementar pantalla de login
3. ⬜ Crear navegación entre pantallas
4. ⬜ Implementar reproductor de video
5. ⬜ Agregar caché con Room
6. ⬜ Implementar búsqueda
7. ⬜ Agregar favoritos
8. ⬜ Testing completo
9. ⬜ Optimización de performance
10. ⬜ Publicar en Play Store

## 💡 Tips de Desarrollo

1. **Usa Preview en Compose**: Agrega `@Preview` a tus composables para verlos en tiempo real
2. **Logcat es tu amigo**: Usa `Log.d()` para debugging
3. **Hot Reload**: Compose soporta hot reload, aprovéchalo
4. **Emulador rápido**: Usa x86_64 images con HAXM/KVM para mejor performance
5. **Git**: Haz commits frecuentes con mensajes descriptivos

## 🚀 ¡Listo para Desarrollar!

Ahora tienes todo configurado para comenzar a desarrollar las aplicaciones Android y Android TV. 

**¿Necesitas ayuda?** Revisa los archivos README.md en cada carpeta del proyecto para documentación específica.

¡Feliz coding! 🎉
