# 📺 Aplicación Android TV - Plataforma de Streaming

## 🎯 Descripción
Aplicación nativa Android TV optimizada para experiencia en pantallas grandes, desarrollada en Kotlin con Leanback Library y Jetpack Compose for TV.

## 🏗️ Arquitectura

### Tecnologías Principales
- **Lenguaje**: Kotlin
- **UI**: Jetpack Compose for TV + Leanback
- **Arquitectura**: MVVM (Model-View-ViewModel)
- **Navegación**: TV Navigation
- **Red**: Retrofit + OkHttp
- **Imágenes**: Coil
- **Video**: ExoPlayer
- **DI**: Hilt (Dagger)
- **Async**: Coroutines + Flow
- **Persistencia**: Room + DataStore

### Estructura del Proyecto
```
android-tv-app/
├── app/
│   ├── src/
│   │   ├── main/
│   │   │   ├── java/com/streaming/tv/
│   │   │   │   ├── data/              # Shared with mobile
│   │   │   │   ├── domain/            # Shared with mobile
│   │   │   │   ├── presentation/
│   │   │   │   │   ├── browse/        # Main browse screen
│   │   │   │   │   ├── details/       # Content details
│   │   │   │   │   ├── player/        # TV player
│   │   │   │   │   ├── search/        # TV search
│   │   │   │   │   └── components/    # TV-specific components
│   │   │   │   ├── di/
│   │   │   │   └── utils/
│   │   │   ├── res/
│   │   │   │   ├── drawable-xhdpi/    # TV banners
│   │   │   │   ├── layout/            # TV layouts
│   │   │   │   └── values/
│   │   │   └── AndroidManifest.xml
│   │   └── test/
│   └── build.gradle.kts
├── gradle/
└── build.gradle.kts
```

## 📦 Dependencias Principales

```kotlin
dependencies {
    // Leanback
    implementation("androidx.leanback:leanback:1.2.0-alpha04")
    implementation("androidx.leanback:leanback-preference:1.2.0-alpha04")
    
    // Compose for TV
    implementation("androidx.tv:tv-foundation:1.0.0-alpha10")
    implementation("androidx.tv:tv-material:1.0.0-alpha10")
    
    // Compose
    implementation("androidx.compose.ui:ui:1.5.4")
    implementation("androidx.compose.material3:material3:1.1.2")
    implementation("androidx.activity:activity-compose:1.8.1")
    
    // TV Navigation
    implementation("androidx.navigation:navigation-compose:2.7.5")
    
    // ViewModel
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.6.2")
    
    // Networking (same as mobile)
    implementation("com.squareup.retrofit2:retrofit:2.9.0")
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    
    // Image Loading
    implementation("io.coil-kt:coil-compose:2.5.0")
    
    // Video Player (optimized for TV)
    implementation("androidx.media3:media3-exoplayer:1.2.0")
    implementation("androidx.media3:media3-ui:1.2.0")
    implementation("androidx.media3:media3-exoplayer-hls:1.2.0")
    implementation("androidx.media3:media3-exoplayer-dash:1.2.0")
    
    // Dependency Injection
    implementation("com.google.dagger:hilt-android:2.48.1")
    kapt("com.google.dagger:hilt-compiler:2.48.1")
    
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

## 🎨 Características Específicas de TV

### Pantallas Principales
1. **Browse Screen** - Pantalla principal con filas de contenido
2. **Details Screen** - Detalles con backdrop grande
3. **Player Screen** - Reproductor fullscreen optimizado
4. **Search Screen** - Búsqueda con teclado en pantalla
5. **Settings Screen** - Configuración de la app

### Navegación D-Pad
```kotlin
@Composable
fun TvBrowseScreen() {
    TvLazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .focusable()
    ) {
        items(contentRows) { row ->
            TvContentRow(
                title = row.title,
                items = row.items,
                onItemClick = { item ->
                    navigateToDetails(item)
                }
            )
        }
    }
}
```

### Componentes TV-Optimizados

#### Card Component
```kotlin
@Composable
fun TvContentCard(
    content: Content,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        onClick = onClick,
        modifier = modifier
            .width(250.dp)
            .height(375.dp)
            .padding(8.dp),
        shape = RoundedCornerShape(8.dp),
        scale = CardDefaults.scale(
            focusedScale = 1.1f,
            pressedScale = 0.95f
        ),
        border = CardDefaults.border(
            focusedBorder = Border(
                border = BorderStroke(3.dp, Color.White),
                shape = RoundedCornerShape(8.dp)
            )
        )
    ) {
        Box {
            AsyncImage(
                model = content.posterUrl,
                contentDescription = content.title,
                modifier = Modifier.fillMaxSize(),
                contentScale = ContentScale.Crop
            )
            
            // Gradient overlay
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(
                        Brush.verticalGradient(
                            colors = listOf(
                                Color.Transparent,
                                Color.Black.copy(alpha = 0.8f)
                            )
                        )
                    )
            )
            
            // Title
            Text(
                text = content.title,
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(16.dp),
                style = MaterialTheme.typography.titleMedium,
                color = Color.White,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}
```

#### Hero Banner
```kotlin
@Composable
fun TvHeroBanner(
    content: Content,
    onPlayClick: () -> Unit,
    onDetailsClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(500.dp)
    ) {
        // Backdrop image
        AsyncImage(
            model = content.backdropUrl,
            contentDescription = null,
            modifier = Modifier.fillMaxSize(),
            contentScale = ContentScale.Crop
        )
        
        // Gradient overlay
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.horizontalGradient(
                        colors = listOf(
                            Color.Black.copy(alpha = 0.9f),
                            Color.Transparent
                        ),
                        endX = 800f
                    )
                )
        )
        
        // Content info
        Column(
            modifier = Modifier
                .align(Alignment.CenterStart)
                .padding(start = 80.dp)
                .width(500.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Text(
                text = content.title,
                style = MaterialTheme.typography.displayLarge,
                color = Color.White,
                fontWeight = FontWeight.Bold
            )
            
            Text(
                text = content.description,
                style = MaterialTheme.typography.bodyLarge,
                color = Color.White.copy(alpha = 0.8f),
                maxLines = 3,
                overflow = TextOverflow.Ellipsis
            )
            
            Row(
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                TvButton(
                    onClick = onPlayClick,
                    text = "Reproducir",
                    icon = Icons.Default.PlayArrow,
                    isPrimary = true
                )
                
                TvButton(
                    onClick = onDetailsClick,
                    text = "Más información",
                    icon = Icons.Default.Info
                )
            }
        }
    }
}
```

## 🎮 Control Remoto

### Manejo de Teclas
```kotlin
@Composable
fun TvPlayerScreen(
    viewModel: PlayerViewModel = hiltViewModel()
) {
    val focusRequester = remember { FocusRequester() }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .focusRequester(focusRequester)
            .focusable()
            .onKeyEvent { keyEvent ->
                when (keyEvent.key) {
                    Key.DirectionCenter, Key.Enter -> {
                        viewModel.togglePlayPause()
                        true
                    }
                    Key.DirectionLeft -> {
                        viewModel.seekBackward()
                        true
                    }
                    Key.DirectionRight -> {
                        viewModel.seekForward()
                        true
                    }
                    Key.Back -> {
                        viewModel.showControls()
                        false // Let system handle back
                    }
                    else -> false
                }
            }
    ) {
        // Player UI
        AndroidView(
            factory = { context ->
                PlayerView(context).apply {
                    player = viewModel.exoPlayer
                    useController = false // Custom controls
                }
            },
            modifier = Modifier.fillMaxSize()
        )
        
        // Custom controls
        AnimatedVisibility(
            visible = viewModel.showControls,
            enter = fadeIn(),
            exit = fadeOut()
        ) {
            TvPlayerControls(
                isPlaying = viewModel.isPlaying,
                currentPosition = viewModel.currentPosition,
                duration = viewModel.duration,
                onPlayPauseClick = { viewModel.togglePlayPause() },
                onSeekTo = { viewModel.seekTo(it) }
            )
        }
    }
    
    LaunchedEffect(Unit) {
        focusRequester.requestFocus()
    }
}
```

## 🎬 Video Player TV

### Características del Player TV
- Controles optimizados para D-Pad
- Overlay de información del contenido
- Navegación entre episodios con D-Pad
- Subtítulos grandes y legibles
- Indicador de buffer
- Reproducción automática
- Skip intro/credits

### Player Controls
```kotlin
@Composable
fun TvPlayerControls(
    isPlaying: Boolean,
    currentPosition: Long,
    duration: Long,
    onPlayPauseClick: () -> Unit,
    onSeekTo: (Long) -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color.Black.copy(alpha = 0.6f))
    ) {
        Column(
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .padding(40.dp)
        ) {
            // Progress bar
            TvProgressBar(
                progress = currentPosition.toFloat() / duration.toFloat(),
                modifier = Modifier.fillMaxWidth()
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Time
                Text(
                    text = "${formatTime(currentPosition)} / ${formatTime(duration)}",
                    style = MaterialTheme.typography.titleMedium,
                    color = Color.White
                )
                
                // Controls
                Row(
                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    TvIconButton(
                        onClick = { onSeekTo(currentPosition - 10000) },
                        icon = Icons.Default.Replay10
                    )
                    
                    TvIconButton(
                        onClick = onPlayPauseClick,
                        icon = if (isPlaying) Icons.Default.Pause else Icons.Default.PlayArrow,
                        isPrimary = true
                    )
                    
                    TvIconButton(
                        onClick = { onSeekTo(currentPosition + 10000) },
                        icon = Icons.Default.Forward10
                    )
                }
            }
        }
    }
}
```

## 🎯 Manifest Configuration

```xml
<?xml version="1.0" encoding="utf-8"?>
<manifest xmlns:android="http://schemas.android.com/apk/res/android">
    
    <!-- TV-specific features -->
    <uses-feature
        android:name="android.hardware.touchscreen"
        android:required="false" />
    <uses-feature
        android:name="android.software.leanback"
        android:required="true" />
    
    <!-- Permissions -->
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
    
    <application
        android:name=".StreamingTvApp"
        android:allowBackup="true"
        android:banner="@drawable/app_banner"
        android:icon="@mipmap/ic_launcher"
        android:label="@string/app_name"
        android:theme="@style/Theme.StreamingTV">
        
        <!-- Main TV activity -->
        <activity
            android:name=".presentation.MainActivity"
            android:exported="true"
            android:screenOrientation="landscape"
            android:configChanges="keyboard|keyboardHidden|navigation">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LEANBACK_LAUNCHER" />
            </intent-filter>
        </activity>
        
        <!-- Player activity -->
        <activity
            android:name=".presentation.player.PlayerActivity"
            android:configChanges="keyboard|keyboardHidden|orientation|screenSize"
            android:launchMode="singleTop"
            android:theme="@style/Theme.StreamingTV.Player" />
    </application>
</manifest>
```

## 🎨 UI/UX Guidelines

### Diseño para TV
- **Overscan Safe Area**: Mantener contenido importante a 48dp de los bordes
- **Tamaño de Fuente**: Mínimo 16sp para legibilidad a distancia
- **Espaciado**: Mayor espacio entre elementos (16-24dp)
- **Foco Visual**: Bordes claros y animaciones de escala
- **Colores**: Alto contraste para visibilidad

### Navegación
- **Vertical**: Navegar entre filas
- **Horizontal**: Navegar dentro de filas
- **Enter/Select**: Seleccionar elemento
- **Back**: Volver atrás
- **Menu**: Abrir opciones

## 🚀 Optimizaciones TV

### Performance
```kotlin
// Preload images for smooth scrolling
@Composable
fun TvContentRow(items: List<Content>) {
    LazyRow(
        contentPadding = PaddingValues(horizontal = 48.dp),
        horizontalArrangement = Arrangement.spacedBy(16.dp),
        flingBehavior = rememberSnapFlingBehavior(
            lazyListState = rememberLazyListState()
        )
    ) {
        items(items) { content ->
            TvContentCard(
                content = content,
                onClick = { /* Navigate */ }
            )
        }
    }
}
```

### Memory Management
- Límite de imágenes en caché
- Liberación de recursos al salir del player
- Lazy loading de contenido

## 📊 Analytics

### Eventos Específicos de TV
```kotlin
sealed class TvAnalyticsEvent {
    data class ContentViewed(val contentId: String, val source: String) : TvAnalyticsEvent()
    data class PlaybackStarted(val contentId: String, val quality: String) : TvAnalyticsEvent()
    data class RemoteButtonPressed(val button: String) : TvAnalyticsEvent()
    data class VoiceSearchUsed(val query: String) : TvAnalyticsEvent()
}
```

## 🧪 Testing en TV

### Emulador TV
```bash
# Crear AVD de TV
avdmanager create avd -n "TV_1080p" -k "system-images;android-33;google_atd;x86_64" -d "tv_1080p"

# Iniciar emulador
emulator -avd TV_1080p
```

### Testing con Control Remoto
- Usar teclado del emulador
- D-Pad: Flechas del teclado
- Enter: Enter/Return
- Back: Escape

## 📝 Diferencias con Mobile

| Característica | Mobile | TV |
|----------------|--------|-----|
| Input | Touch | D-Pad/Remote |
| Orientación | Portrait/Landscape | Landscape only |
| Distancia de visión | 30-40cm | 2-3m |
| Tamaño de fuente | 14-16sp | 18-24sp |
| Navegación | Gestos | Foco |
| Layout | Flexible | 10-foot UI |
| Banner | Icon | Banner (320x180) |

## 🎯 Roadmap TV

### Fase 1 - MVP ✅
- [x] Browse screen con Leanback
- [x] Player optimizado para TV
- [x] Navegación con D-Pad
- [x] Detalles de contenido

### Fase 2 - Mejoras
- [ ] Búsqueda por voz
- [ ] Recomendaciones en home
- [ ] Picture-in-Picture
- [ ] Live channels integration

### Fase 3 - Avanzado
- [ ] Google Assistant integration
- [ ] Android TV Home Screen channels
- [ ] 4K/HDR playback
- [ ] Multi-user profiles
