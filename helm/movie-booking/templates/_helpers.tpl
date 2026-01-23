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
{{- if .Values.serviceAccount.create }}
{{- default (include "movie-booking.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Database URL
*/}}
{{- define "movie-booking.databaseUrl" -}}
postgresql+asyncpg://{{ .Values.postgresql.auth.username }}:{{ .Values.postgresql.auth.password }}@{{ .Release.Name }}-postgresql:5432/{{ .Values.postgresql.auth.database }}
{{- end }}

{{/*
Redis URL
*/}}
{{- define "movie-booking.redisUrl" -}}
redis://:{{ .Values.redis.auth.password }}@{{ .Release.Name }}-redis-master:6379
{{- end }}

{{/*
RabbitMQ URL
*/}}
{{- define "movie-booking.rabbitmqUrl" -}}
amqp://{{ .Values.rabbitmq.auth.username }}:{{ .Values.rabbitmq.auth.password }}@{{ .Release.Name }}-rabbitmq:5672/
{{- end }}
