{{/*
Expand the name of the chart.
*/}}
{{- define "movie-booking.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
*/}}
{{- define "movie-booking.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "movie-booking.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "movie-booking.labels" -}}
helm.sh/chart: {{ include "movie-booking.chart" . }}
{{ include "movie-booking.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "movie-booking.selectorLabels" -}}
app.kubernetes.io/name: {{ include "movie-booking.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "movie-booking.serviceAccountName" -}}
{{- if .Values.serviceAccount }}
{{- if .Values.serviceAccount.create }}
{{- default (include "movie-booking.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- else }}
{{- "default" }}
{{- end }}
{{- end }}

{{/*
Database URL - For Bitnami PostgreSQL dependency
*/}}
{{- define "movie-booking.databaseUrl" -}}
{{- if .Values.postgresql.enabled -}}
postgresql+asyncpg://{{ .Values.postgresql.auth.username }}:{{ .Values.postgresql.auth.password }}@{{ .Release.Name }}-postgresql:5432/{{ .Values.postgresql.auth.database }}
{{- else -}}
postgresql+asyncpg://user:password@postgres:5432/db
{{- end -}}
{{- end }}

{{/*
Redis URL - For Bitnami Redis dependency or local
*/}}
{{- define "movie-booking.redisUrl" -}}
{{- if .Values.localRedis -}}
redis://:{{ .Values.localRedis.password }}@redis-service:6379
{{- else if .Values.redis.enabled -}}
redis://:{{ .Values.redis.auth.password }}@{{ .Release.Name }}-redis-master:6379
{{- else -}}
redis://redis:6379
{{- end -}}
{{- end }}

{{/*
RabbitMQ URL - For Bitnami RabbitMQ dependency or local
*/}}
{{- define "movie-booking.rabbitmqUrl" -}}
{{- if .Values.localRabbitmq -}}
amqp://{{ .Values.localRabbitmq.user }}:{{ .Values.localRabbitmq.password }}@rabbitmq-service:5672/
{{- else if .Values.rabbitmq.enabled -}}
amqp://{{ .Values.rabbitmq.auth.username }}:{{ .Values.rabbitmq.auth.password }}@{{ .Release.Name }}-rabbitmq:5672/
{{- else -}}
amqp://guest:guest@rabbitmq:5672/
{{- end -}}
{{- end }}

{{/*
Auth Service Database URL
*/}}
{{- define "movie-booking.authDatabaseUrl" -}}
{{- if .Values.localDatabase -}}
postgresql+asyncpg://{{ .Values.localDatabase.auth.user }}:{{ .Values.localDatabase.auth.password }}@postgres-auth-service:5432/{{ .Values.localDatabase.auth.database }}
{{- else -}}
{{ include "movie-booking.databaseUrl" . }}
{{- end -}}
{{- end }}

{{/*
Movie Service Database URL
*/}}
{{- define "movie-booking.movieDatabaseUrl" -}}
{{- if .Values.localDatabase -}}
postgresql+asyncpg://{{ .Values.localDatabase.movie.user }}:{{ .Values.localDatabase.movie.password }}@postgres-movie-service:5432/{{ .Values.localDatabase.movie.database }}
{{- else -}}
{{ include "movie-booking.databaseUrl" . }}
{{- end -}}
{{- end }}

{{/*
Booking Service Database URL
*/}}
{{- define "movie-booking.bookingDatabaseUrl" -}}
{{- if .Values.localDatabase -}}
postgresql+asyncpg://{{ .Values.localDatabase.booking.user }}:{{ .Values.localDatabase.booking.password }}@postgres-booking-service:5432/{{ .Values.localDatabase.booking.database }}
{{- else -}}
{{ include "movie-booking.databaseUrl" . }}
{{- end -}}
{{- end }}

{{/*
Payment Service Database URL
*/}}
{{- define "movie-booking.paymentDatabaseUrl" -}}
{{- if .Values.localDatabase -}}
postgresql+asyncpg://{{ .Values.localDatabase.payment.user }}:{{ .Values.localDatabase.payment.password }}@postgres-payment-service:5432/{{ .Values.localDatabase.payment.database }}
{{- else -}}
{{ include "movie-booking.databaseUrl" . }}
{{- end -}}
{{- end }}

{{/*
Notification Service Database URL
*/}}
{{- define "movie-booking.notificationDatabaseUrl" -}}
{{- if .Values.localDatabase -}}
postgresql+asyncpg://{{ .Values.localDatabase.notification.user }}:{{ .Values.localDatabase.notification.password }}@postgres-notification-service:5432/{{ .Values.localDatabase.notification.database }}
{{- else -}}
{{ include "movie-booking.databaseUrl" . }}
{{- end -}}
{{- end }}
