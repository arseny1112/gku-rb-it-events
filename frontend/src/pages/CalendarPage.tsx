import React, { useState, useEffect, useMemo, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { createEvent } from '../api/clients'
import CalendarWidget from '../components/CalendarWidget'

const CreateEventPage: React.FC = () => {
  const navigate = useNavigate()
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)
  const [selectedCalendarDate, setSelectedCalendarDate] = useState<Date | undefined>(undefined)
  
  const [showCalendar, setShowCalendar] = useState(false)
  const dateFieldRef = useRef<HTMLDivElement>(null)

  const [showCategoryDropdown, setShowCategoryDropdown] = useState(false)
  const [now, setNow] = useState<Date>(new Date());

  

  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 60_000);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dateFieldRef.current && !dateFieldRef.current.contains(event.target as Node)) {
        setShowCalendar(false)
      }
    }

    if (showCalendar) {
      document.addEventListener('mousedown', handleClickOutside)
      return () => document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [showCalendar])

  const [formData, setFormData] = useState({
    title: '',
    date: '',
    time: '',
    category: '',
    location: '',
    description: '',
  })

  useEffect(() => {
    if (formData.date) {
      const [d, m, y] = formData.date.split('/').map(Number)
      if (d && m && y) {
        setSelectedCalendarDate(new Date(y, m - 1, d))
      }
    }
  }, [formData.date])
  
  const isPastEvent = useMemo(() => {
    if (!formData.date || !formData.time) return false;
    
    const [dd, mm, yyyy] = formData.date.split('/').map(Number);
    const [hours, minutes] = formData.time.split(':').map(Number);
    
    if (!dd || !mm || !yyyy || isNaN(hours) || isNaN(minutes)) return false;
    
    const selected = new Date(yyyy, mm - 1, dd, hours, minutes);
    return selected < now;
  }, [formData.date, formData.time, now]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    setFormData({ ...formData, [e.target.name]: e.target.value })
  }

  const handleDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    let value = e.target.value.replace(/[^0-9]/g, '')
    
    if (value.length >= 2 && value.length < 4) {
      value = value.slice(0, 2) + '/' + value.slice(2)
    } else if (value.length >= 4 && value.length < 6) {
      value = value.slice(0, 2) + '/' + value.slice(2, 4) + '/' + value.slice(4)
    } else if (value.length >= 6) {
      value = value.slice(0, 2) + '/' + value.slice(2, 4) + '/' + value.slice(4, 8)
    }
    
    if (value.length <= 10) {
      setFormData({ ...formData, date: value })
    }
  }

  const handleTimeSelect = (time: string) => {
    setFormData({ ...formData, time })
  }

  const clearForm = () => {
    if (window.confirm('Очистить все поля?')) {
      setFormData({
        title: '',
        date: '',
        time: '',
        category: '',
        location: '',
        description: '',
      })
      setSelectedCalendarDate(undefined)
      setError(null)
      setSuccess(null)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)
    setError(null)
    setSuccess(null)
  
    if (!formData.title || !formData.date || !formData.time) {
      setError('Заполните название, дату и время')
      setIsLoading(false)
      return
    }
  
    if (isPastEvent) {
      setError('Нельзя создать событие на прошедшее время')
      setIsLoading(false)
      return
    }
  
    try {
      const [dd, mm, yyyy] = formData.date.split('/')
      const formattedDate = `${yyyy}-${mm}-${dd}`
      const startDatetime = `${formattedDate} ${formData.time}:00`
      
      await createEvent({
        title: formData.title,
        start_datetime: startDatetime,
        end_datetime: startDatetime,
        category_id: formData.category ? Number(formData.category) : 1,
        description: formData.description,
        location: formData.location,
      })

      localStorage.removeItem('events')
      setSuccess('Событие успешно создано!')
      setTimeout(() => {
        navigate('/')
      }, 1500)
    } catch (err: any) {
      setError(err.response?.data?.error || 'Ошибка при создании события')
    } finally {
      setIsLoading(false)
    }
  }

  const quickTimes = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00']

  return (
    <div className="min-h-screen bg-[#F4F5F7] p-[24px]">
      {/* Затемнение при загрузке */}
      {isLoading && (
        <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 flex items-center gap-3 shadow-lg">
            <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-[#05591D]"></div>
            <span className="text-[16px] text-[#0B1C30]">Создание события...</span>
          </div>
        </div>
      )}

      <div>
        {/* Header Section */}
        <div className="mb-[32px]">
          <h1 className="text-[32px] font-bold text-[#0B1C30]">
            Создание события
          </h1>
          <p className="text-[14px] text-[#5F4900] mt-2">
            Заполните форму для создания нового мероприятия
          </p>
        </div>

        {/* Success Message */}
        {success && (
          <div className="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-lg text-green-700">
            <div className="flex items-center gap-2">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M20 6L9 17L4 12" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round"/>
              </svg>
              {success}
            </div>
          </div>
        )}

        {/* Error Message */}
        {error && (
          <div className="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-lg text-red-700">
            <div className="flex items-center gap-2">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              {error}
            </div>
          </div>
        )}

        {/* Индикатор обязательных полей */}
        <div className="mb-4 text-sm text-gray-500">
          <span className="text-red-500">*</span> — обязательные поля
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          {/* Left Column: Form Fields */}
          <div className="lg:col-span-8 bg-white p-[24px] rounded-[15px] shadow-sm border border-[#C0C9BB]">
            <form onSubmit={handleSubmit} className="gap-[24px] flex flex-col">
              
              {/* Event Name */}
              <div>
                <label className="block text-xs font-semibold text-[#40493E] uppercase tracking-wider mb-3">
                  Название события <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  name="title"
                  value={formData.title}
                  onChange={handleInputChange}
                  placeholder="Например: Планёрка, Встреча с партнёрами..."
                  className="w-full text-[16px] placeholder-[#C0C9BB] border-b-[1px] border-[#C0C9BB] focus:border-[#05591D] outline-none pb-2 transition-colors bg-transparent"
                />
              </div>

              {/* Date & Category Row */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-12">
                {/* Date */}
                <div>
                  <label className="block text-xs font-semibold text-[#40493E] uppercase tracking-wider mb-3">
                    Дата и время <span className="text-red-500">*</span>
                  </label>
                  
                  <div ref={dateFieldRef} className="relative">
                    <div className="flex gap-3 border-b-[1px] border-[#C0C9BB]">
                      <div 
                        className="relative flex items-center focus-within:border-[#05591D] transition-colors w-32 cursor-pointer"
                        onClick={() => setShowCalendar(true)}
                      >
                        <svg width="18" height="24" viewBox="0 0 18 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M2 20C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V4C0 3.45 0.195833 2.97917 0.5875 2.5875C0.979167 2.19583 1.45 2 2 2H3V0H5V2H13V0H15V2H16C16.55 2 17.0208 2.19583 17.4125 2.5875C17.8042 2.97917 18 3.45 18 4V18C18 18.55 17.8042 19.0208 17.4125 19.4125C17.0208 19.8042 16.55 20 16 20H2ZM2 18H16V8H2V18ZM2 6H16V4H2V6ZM2 6V4V6Z" fill="#C0C9BB"/>
                        </svg>
                        <input
                          name="date"
                          type="text"
                          value={formData.date || ''}
                          onChange={handleDateChange}
                          placeholder="dd/mm/yyyy"
                          className="w-full ml-[15px] text-[16px] text-[#0B1C30] py-2 outline-none bg-transparent"
                        />
                      </div>

                      <div className="relative flex-1">
                        <input
                          type="text"
                          value={formData.time}
                          onChange={(e) => {
                            let value = e.target.value.replace(/[^0-9]/g, '')
                            
                            if (value.length >= 1) {
                              const firstDigit = parseInt(value[0])
                              if (firstDigit > 2) {
                                value = '0' + value[0]
                              }
                            }
                            
                            if (value.length >= 2) {
                              const hours = parseInt(value.slice(0, 2))
                              if (hours > 23) {
                                value = '23' + value.slice(2)
                              }
                            }
                            
                            if (value.length >= 3) {
                              value = value.slice(0, 2) + ':' + value.slice(2, 4)
                            }
                            
                            if (value.length >= 6) {
                              const minutes = parseInt(value.slice(3, 5))
                              if (minutes > 59) {
                                value = value.slice(0, 3) + '59'
                              }
                            }
                            
                            if (value.length <= 5) {
                              setFormData({ ...formData, time: value })
                            }
                          }}
                          onBlur={() => {
                            if (formData.time && formData.time.includes(':')) {
                              let [hours, minutes] = formData.time.split(':')
                              let h = parseInt(hours) || 0
                              let m = parseInt(minutes) || 0
                              
                              if (h > 23) h = 23
                              if (m > 59) m = 59
                              
                              const formattedTime = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`
                              setFormData({ ...formData, time: formattedTime })
                            }
                          }}
                          placeholder="--:--"
                          className="w-full text-[16px] text-[#0B1C30] py-2 outline-none bg-transparent placeholder-[#C0C9BB]"
                        />
                      </div>
                    </div>

                    {/* Быстрый выбор времени */}
                    <div className="flex flex-wrap gap-2 mt-3">
                      {quickTimes.map(t => (
                        <button
                          key={t}
                          type="button"
                          onClick={() => handleTimeSelect(t)}
                          className={`px-3 py-1 text-xs rounded-full transition-colors ${
                            formData.time === t 
                              ? 'bg-[#05591D] text-white' 
                              : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                          }`}
                        >
                          {t}
                        </button>
                      ))}
                    </div>

                    {showCalendar && (
                      <div className="absolute top-[-126px] w-full left-0 mt-2 z-50 shadow-xl">
                        <CalendarWidget
                          selectedDate={selectedCalendarDate}
                          onDateSelect={(date) => {
                            const yyyy = date.getFullYear()
                            const mm = String(date.getMonth() + 1).padStart(2, '0')
                            const dd = String(date.getDate()).padStart(2, '0')
                            setFormData(prev => ({ ...prev, date: `${dd}/${mm}/${yyyy}` }))
                            setShowCalendar(false)
                          }}
                          showEvents={false}
                          minDate={new Date()}
                        />
                      </div>
                    )}
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-[#40493E] uppercase tracking-wider mb-3">
                    Категория
                  </label>
                  <div className="relative">
                    <button
                      type="button"
                      onClick={() => setShowCategoryDropdown(!showCategoryDropdown)}
                      className="w-full text-[16px] text-left text-[#0B1C30] border-b-[1px] border-[#C0C9BB] focus:border-[#05591D] outline-none pb-2 transition-colors bg-transparent flex items-center justify-between"
                    >
                      <span className={formData.category ? 'text-[#0B1C30]' : 'text-[#C0C9BB]'}>
                        {formData.category === '1' && 'Совещание'}
                        {formData.category === '2' && 'Встреча (переговоры)'}
                        {formData.category === '3' && 'Конференция'}
                        {formData.category === '4' && 'Обучение'}
                        {!formData.category && 'Выберите категорию'}
                      </span>
                      <svg className={`w-5 h-5 text-[#C0C9BB] transition-transform ${showCategoryDropdown ? 'rotate-180' : ''}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="6 9 12 15 18 9" />
                      </svg>
                    </button>

                    {showCategoryDropdown && (
                      <div className="absolute top-full left-0 right-0 mt-1 bg-white rounded-[8px] shadow-lg border border-[#E2E8F0] z-50 overflow-hidden">
                        {[
                          { id: '1', name: 'Совещание' },
                          { id: '2', name: 'Встреча (переговоры)' },
                          { id: '3', name: 'Конференция' },
                          { id: '4', name: 'Обучение' },
                        ].map((cat) => (
                          <div
                            key={cat.id}
                            onClick={() => {
                              setFormData({ ...formData, category: cat.id })
                              setShowCategoryDropdown(false)
                            }}
                            className="px-4 py-3 hover:bg-[#F4F5F7] cursor-pointer text-[14px] text-[#0B1C30] transition-colors"
                          >
                            {cat.name}
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Location */}
              <div>
                <label className="block text-xs font-semibold text-[#40493E] uppercase tracking-wider mb-3">
                  Место проведения
                </label>
                <div className="relative flex items-center border-b-[1px] border-[#C0C9BB] focus-within:border-[#05591D] transition-colors">
                  <svg className="flex-shrink-0 mr-3 text-[#C0C9BB]" width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 10C8.55 10 9.02083 9.80417 9.4125 9.4125C9.80417 9.02083 10 8.55 10 8C10 7.45 9.80417 6.97917 9.4125 6.5875C9.02083 6.19583 8.55 6 8 6C7.45 6 6.97917 6.19583 6.5875 6.5875C6.19583 6.97917 6 7.45 6 8C6 8.55 6.19583 9.02083 6.5875 9.4125C6.97917 9.80417 7.45 10 8 10ZM8 17.35C10.0333 15.4833 11.5417 13.7875 12.525 12.2625C13.5083 10.7375 14 9.38333 14 8.2C14 6.38333 13.4208 4.89583 12.2625 3.7375C11.1042 2.57917 9.68333 2 8 2C6.31667 2 4.89583 2.57917 3.7375 3.7375C2.57917 4.89583 2 6.38333 2 8.2C2 9.38333 2.49167 10.7375 3.475 12.2625C4.45833 13.7875 5.96667 15.4833 8 17.35ZM8 20C5.31667 17.7167 3.3125 15.5958 1.9875 13.6375C0.6625 11.6792 0 9.86667 0 8.2C0 5.7 0.804167 3.70833 2.4125 2.225C4.02083 0.741667 5.88333 0 8 0C10.1167 0 11.9792 0.741667 13.5875 2.225C15.1958 3.70833 16 5.7 16 8.2C16 9.86667 15.3375 11.6792 14.0125 13.6375C12.6875 15.5958 10.6833 17.7167 8 20Z" fill="currentColor"/>
                  </svg>
                  <input
                    type="text"
                    name="location"
                    value={formData.location}
                    onChange={handleInputChange}
                    placeholder="Укажите кабинет, адрес или ссылку..."
                    className="w-full text-[16px] text-[#0B1C30] placeholder-[#C0C9BB] py-2 outline-none bg-transparent"
                  />
                </div>
              </div>

              {/* Description */}
              <div>
                <label className="block text-xs font-semibold text-[#40493E] uppercase tracking-wider mb-3">
                  Описание
                </label>
                <textarea
                  name="description"
                  value={formData.description}
                  onChange={handleInputChange}
                  placeholder="Добавьте детали события..."
                  rows={4}
                  className="w-full text-[16px] max-h-[122px] text-[#0B1C30] placeholder-[#C0C9BB] border-2 border-[#E2E8F0] rounded-[12px] focus:border-[#05591D] outline-none p-4 transition-colors resize-none"
                />
              </div>

            </form>
          </div>

          {/* Right Column: Actions */}
          <div className="lg:col-span-4 flex flex-col gap-6">
            
            {/* Напоминания Card */}
            <div className="bg-white p-6 rounded-[20px] shadow-sm border border-[#C0C9BB]">
              <h3 className="text-[20px] font-bold text-[#0B1C30] mb-4">
                Напоминания
              </h3>
              
              <div className="bg-[#E8F0FE] py-[3px] px-[12px] mb-6 border-l-2 border-[#015FAF]">
                <p className="text-[14px] text-[#0B1C30] leading-relaxed">
                  Автоматически будут отправлены уведомления за 1 день и за 1 час до начала события
                </p>
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex flex-col gap-4 rounded-[15px] bg-white p-[24px] border border-[#C0C9BB]">
              <button 
                type="submit"
                onClick={handleSubmit}
                disabled={isLoading || isPastEvent}
                className="max-h-[48px] w-full py-[12px] bg-[#05591D] hover:bg-[#034a18] text-white rounded-[14px] font-medium text-[16px] transition-colors flex items-center justify-center gap-2 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isLoading ? (
                  <>
                    <svg className="animate-spin" width="18" height="18" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="2" strokeDasharray="30" strokeDashoffset="30">
                        <animate attributeName="stroke-dashoffset" values="30;0" dur="1s" repeatCount="indefinite"/>
                      </circle>
                    </svg>
                    Создание...
                  </>
                ) : (
                  <>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                      <polyline points="17 21 17 13 7 13 7 21"/>
                      <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    Создать событие
                  </>
                )}
              </button>

              <button 
                type="button"
                onClick={clearForm}
                disabled={isLoading}
                className="py-[12px] bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-[14px] font-medium transition-colors disabled:opacity-50"
              >
                Очистить форму
              </button>

              <button 
                type="button"
                onClick={() => {
                  if (window.confirm('Вы уверены, что хотите отменить создание события?')) {
                    navigate('/')
                  }
                }}
                disabled={isLoading}
                className="py-[12px] bg-white border border-[#EF4444] text-[#EF4444] hover:bg-red-50 rounded-[14px] font-medium text-[16px] transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
              >
                <svg width="14" height="15" viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M2.5 15C2.04167 15 1.64931 14.8368 1.32292 14.5104C0.996528 14.184 0.833333 13.7917 0.833333 13.3333V2.5H0V0.833333H4.16667V0H9.16667V0.833333H13.3333V2.5H12.5V13.3333C12.5 13.7917 12.3368 14.184 12.0104 14.5104C11.684 14.8368 11.2917 15 10.8333 15H2.5ZM10.8333 2.5H2.5V13.3333H10.8333V2.5ZM4.16667 11.6667H5.83333V4.16667H4.16667V11.6667ZM7.5 11.6667H9.16667V4.16667H7.5V11.6667Z" fill="#BA1A1A"/>
                </svg>
                Отмена
              </button>
            </div>

          </div>
        </div>
      </div>
    </div>
  )
}

export default CreateEventPage