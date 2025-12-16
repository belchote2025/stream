# 📱 Aplicación Android - Plataforma de Streaming

## 🎯 Descripción
Aplicación nativa Android para la plataforma de streaming, desarrollada en Kotlin con arquitectura MVVM y Jetpack Compose.

## 🏗️ Arquitectura

### Tecnologías Principales
- **Lenguaje**: Kotlin
- **UI**: Jetpack Compose
- **Arquitectura**: MVVM (Model-View-ViewModel)
- **Navegación**: Jetpack Navigation Compose
- **Red**: Retrofit + OkHttp
- **Imágenes**: Coil
- **Video**: ExoPlayer
- **DI**: Hilt (Dagger)
- **Async**: Coroutines + Flow
- **Persistencia**: Room + DataStore

### Estructura del Proyecto
```
android-app/
├── app/
│   ├── src/
│   │   ├── main/
│   │   │   ├── java/com/streaming/
│   │   │   │   ├── data/
│   │   │   │   │   ├── api/          # API clients
│   │   │   │   │   ├── model/        # Data models
│   │   │   │   │   ├── repository/   # Repositories
│   │   │   │   │   └── local/        # Local database
│   │   │   │   ├── domain/
│   │   │   │   │   ├── model/        # Domain models
│   │   │   │   │   └── usecase/      # Use cases
│   │   │   │   ├── presentation/
│   │   │   │   │   ├── home/         # Home screen
│   │   │   │   │   ├── player/       # Video player
│   │   │   │   │   ├── search/       # Search
│   │   │   │   │   ├── profile/      # User profile
│   │   │   │   │   └── components/   # Reusable components
│   │   │   │   ├── di/               # Dependency injection
│   │   │   │   └── utils/            # Utilities
│   │   │   ├── res/
│   │   │   │   ├── drawable/         # Icons & images
│   │   │   │   ├── values/           # Strings, colors, themes
│   │   │   │   └── xml/              # Network security config
│   │   │   └── AndroidManifest.xml
│   │   └── test/                     # Unit tests
│   └── build.gradle.kts
├── gradle/
└── build.gradle.kts
```

## 📦 Dependencias Principales

```kotlin
dependencies {
    // Compose
    implementation("androidx.compose.ui:ui:1.5.4")
    implementation("androidx.compose.material3:material3:1.1.2")
    implementation("androidx.compose.ui:ui-tooling-preview:1.5.4")
    implementation("androidx.activity:activity-compose:1.8.1")
    
    // Navigation
    implementation("androidx.navigation:navigation-compose:2.7.5")
    
    // ViewModel
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.6.2")
    implementation("androidx.lifecycle:lifecycle-runtime-compose:2.6.2")
    
    // Networking
    implementation("com.squareup.retrofit2:retrofit:2.9.0")
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")
    
    // Image Loading
    implementation("io.coil-kt:coil-compose:2.5.0")
    
    // Video Player
    implementation("androidx.media3:media3-exoplayer:1.2.0")
    implementation("androidx.media3:media3-ui:1.2.0")
    implementation("androidx.media3:media3-exoplayer-hls:1.2.0")
    
    // Dependency Injection
    implementation("com.google.dagger:hilt-android:2.48.1")
    kapt("com.google.dagger:hilt-compiler:2.48.1")
    implementation("androidx.hilt:hilt-navigation-compose:1.1.0")
    
    // Coroutines
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3")
    
    // Room
    implementation("androidx.room:room-runtime:2.6.1")
    implementation("androidx.room:room-ktx:2.6.1")
    kapt("androidx.room:room-compiler:2.6.1")
    
    // DataStore
    implementation("androidx.datastore:datastore-preferences:1.0.0")
}
```

## 🎨 Características

### Pantallas Principales
1. **Splash Screen** - Pantalla de inicio con logo animado
2. **Home** - Carrusel de contenido destacado + filas de categorías
3. **Search** - Búsqueda con autocompletado
4. **Player** - Reproductor de video con controles personalizados
5. **Details** - Detalles del contenido con trailer
6. **Profile** - Perfil de usuario y configuración
7. **My List** - Lista de favoritos
8. **Continue Watching** - Continuar viendo

### Funcionalidades
- ✅ Autenticación (Login/Register)
- ✅ Navegación fluida con animaciones
- ✅ Reproducción de video con ExoPlayer
- ✅ Descarga offline (opcional)
- ✅ Sincronización de progreso
- ✅ Notificaciones push
- ✅ Modo oscuro/claro
- ✅ Soporte multi-idioma
- ✅ Chromecast support

## 🎯 API Integration

### Base URL
```kotlin
const val BASE_URL = "https://tu-dominio.com/streaming-platform/"
```

### Endpoints Principales
```kotlin
interface StreamingApi {
    @GET("api/content/index.php")
    suspend fun getContent(
        @Query("type") type: String,
        @Query("sort") sort: String,
        @Query("limit") limit: Int
    ): Response<ContentResponse>
    
    @GET("api/content/index.php")
    suspend fun getContentById(@Query("id") id: Int): Response<ContentDetail>
    
    @POST("api/auth/login.php")
    suspend fun login(@Body credentials: LoginRequest): Response<AuthResponse>
    
    @GET("api/search.php")
    suspend fun search(@Query("q") query: String): Response<SearchResponse>
    
    @POST("api/playback/progress.php")
    suspend fun updateProgress(@Body progress: ProgressUpdate): Response<Unit>
}
```

## 🎬 Video Player

### Características del Player
- Controles personalizados estilo Netflix
- Gestos: swipe para adelantar/retroceder, volumen, brillo
- Picture-in-Picture (PiP)
- Subtítulos
- Selección de calidad
- Reproducción automática del siguiente episodio
- Recuerdo de posición

## 🔐 Seguridad

### Network Security Config
```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">localhost</domain>
        <domain includeSubdomains="true">10.0.2.2</domain>
    </domain-config>
</network-security-config>
```

### Almacenamiento Seguro
- Tokens en DataStore encriptado
- Certificados SSL pinning
- Ofuscación de código con ProGuard

## 📱 UI/UX

### Tema
```kotlin
@Composable
fun StreamingTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colors = if (darkTheme) {
        darkColorScheme(
            primary = NetflixRed,
            background = Color(0xFF141414),
            surface = Color(0xFF1F1F1F),
            onPrimary = Color.White,
            onBackground = Color.White
        )
    } else {
        lightColorScheme(
            primary = NetflixRed,
            background = Color.White,
            surface = Color(0xFFF5F5F5)
        )
    }
    
    MaterialTheme(
        colorScheme = colors,
        typography = Typography,
        content = content
    )
}
```

## 🚀 Compilación

### Debug Build
```bash
./gradlew assembleDebug
```

### Release Build
```bash
./gradlew assembleRelease
```

### Instalación
```bash
adb install app/build/outputs/apk/debug/app-debug.apk
```

## 📊 Performance

### Optimizaciones
- Lazy loading de imágenes
- Paginación de contenido
- Caché de imágenes con Coil
- Compresión de imágenes
- Minificación de código

## 🧪 Testing

### Unit Tests
```kotlin
@Test
fun `test login success`() = runTest {
    // Test implementation
}
```

### UI Tests
```kotlin
@Test
fun testHomeScreenDisplaysContent() {
    composeTestRule.setContent {
        HomeScreen()
    }
    composeTestRule.onNodeWithText("Películas populares").assertIsDisplayed()
}
```

## 📝 Notas de Desarrollo

### Requisitos
- Android Studio Hedgehog | 2023.1.1 o superior
- Kotlin 1.9.0 o superior
- Gradle 8.0 o superior
- Android SDK 24+ (Android 7.0+)
- Target SDK 34 (Android 14)

### Configuración Inicial
1. Clonar el repositorio
2. Abrir en Android Studio
3. Sincronizar Gradle
4. Configurar BASE_URL en `Constants.kt`
5. Ejecutar en emulador o dispositivo

## 🎯 Roadmap

### Fase 1 - MVP ✅
- [x] Estructura del proyecto
- [x] Integración con API
- [x] Pantallas principales
- [x] Reproductor de video

### Fase 2 - Mejoras
- [ ] Descarga offline
- [ ] Chromecast
- [ ] Notificaciones push
- [ ] Perfiles múltiples

### Fase 3 - Avanzado
- [ ] Recomendaciones con ML
- [ ] Social features
- [ ] Live streaming
- [ ] 4K/HDR support
